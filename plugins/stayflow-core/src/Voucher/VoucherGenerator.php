<?php

declare(strict_types=1);

namespace StayFlow\Voucher;

use StayFlow\Booking\CancellationManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.3.4
 * RU: Если токен пустой — ссылка управления не рендерится. Выводится fallback-текст.
 * EN: If token is empty, manage link is not rendered. Fallback text is displayed.
 */
final class VoucherGenerator
{
    private const BS_EXT_REF_META = '_bs_external_reservation_ref';

    // ==========================================
    // Helpers
    // ==========================================

    public static function getVoucherNumber(int $bookingId): string
    {
        if (function_exists('bsbt_get_display_booking_ref')) {
            return (string) bsbt_get_display_booking_ref($bookingId);
        }
        
        $ext = trim((string) get_post_meta($bookingId, self::BS_EXT_REF_META, true));
        if ($ext !== '') return $ext;

        $candidateKeys = ['bs_external_reservation', 'external_reservation_number', 'bs_booking_number', 'reservation_number'];
        foreach ($candidateKeys as $key) {
            $val = trim((string) get_post_meta($bookingId, $key, true));
            if ($val !== '') return $val;
        }

        $internal = trim((string) get_post_meta($bookingId, 'bs_internal_booking_number', true));
        if ($internal !== '') return $internal;

        return (string) $bookingId;
    }

    public static function tryLoadPdfEngine(): string
    {
        if (class_exists('\Mpdf\Mpdf')) return 'mpdf';
        if (class_exists('\Dompdf\Dompdf')) return 'dompdf';
        
        $mpdfCandidates = [
            WP_PLUGIN_DIR . '/motopress-hotel-booking-pdf-invoices/vendor/autoload.php', 
            WP_PLUGIN_DIR . '/hotel-booking-pdf-invoices/vendor/autoload.php'
        ];
        
        foreach ($mpdfCandidates as $autoload) {
            if (is_file($autoload)) {
                require_once $autoload;
                if (class_exists('\Mpdf\Mpdf')) return 'mpdf';
            }
        }
        
        $dompdfAutoload = WP_PLUGIN_DIR . '/mphb-invoices/vendors/dompdf/autoload.inc.php';
        if (is_file($dompdfAutoload)) {
            require_once $dompdfAutoload;
            if (class_exists('\Dompdf\Dompdf')) return 'dompdf';
        }
        
        return '';
    }

    // ==========================================
    // PDF Generation
    // ==========================================

    public static function generatePdfFile(int $bookingId, string $suffix = ''): string
    {
        if ($bookingId <= 0) return '';
        
        $html = self::renderHtml($bookingId);
        if (!$html) return '';

        $uploadDir = wp_upload_dir();
        $dir = trailingslashit($uploadDir['basedir']) . 'bs-vouchers';
        if (!is_dir($dir)) wp_mkdir_p($dir);

        $suffixStr = $suffix ? '-' . $suffix : '-' . date('Ymd-His');
        $file = trailingslashit($dir) . 'Voucher-' . $bookingId . $suffixStr . '.pdf';

        if ($suffix === 'PAIDEMAIL' && is_file($file) && filesize($file) > 800) {
            return $file;
        }

        $engine = self::tryLoadPdfEngine();
        
        try {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', '300');
            
            if ($engine === 'mpdf' && class_exists('\Mpdf\Mpdf')) {
                $mpdf = new \Mpdf\Mpdf(['format'=>'A4','margin_left'=>12,'margin_right'=>12,'margin_top'=>14,'margin_bottom'=>14]);
                $mpdf->WriteHTML($html);
                $mpdf->Output($file, \Mpdf\Output\Destination::FILE);
            } elseif ($engine === 'dompdf' && class_exists('\Dompdf\Dompdf')) {
                // TODO: Replace remote logo URL with local filepath (WP_CONTENT_DIR) so we can set isRemoteEnabled to false.
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled'=>true]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4','portrait');
                $dompdf->render();
                file_put_contents($file, $dompdf->output());
            } else { 
                return ''; 
            }
        } catch (\Throwable $e) { 
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[StayFlow PDF Generator Error] ' . $e->getMessage());
            }
            return ''; 
        }

        return (is_file($file) && filesize($file) > 800) ? $file : '';
    }

    // ==========================================
    // HTML Rendering
    // ==========================================

    public static function renderHtml(int $bookingId): string
    {
        $owner = ['name'=>'','phone'=>'','email'=>'','address'=>'','doorbell'=>''];
        $roomTypeId = 0;

        if (function_exists('MPHB')) {
            try {
                $booking = \MPHB()->getBookingRepository()->findById($bookingId);
                if ($booking) {
                    $reserved = $booking->getReservedRooms();
                    if (!empty($reserved)) {
                        $first = reset($reserved);
                        $roomTypeId = (int) $first->getRoomTypeId();
                        if ($roomTypeId > 0) {
                            $owner['name']     = trim((string)get_post_meta($roomTypeId, 'owner_name', true));
                            $owner['phone']    = trim((string)get_post_meta($roomTypeId, 'owner_phone', true));
                            $owner['email']    = trim((string)get_post_meta($roomTypeId, 'owner_email', true));
                            $owner['address']  = trim((string)get_post_meta($roomTypeId, 'address', true));
                            $owner['doorbell'] = trim((string)get_post_meta($roomTypeId, 'doorbell_name', true));
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        $ownerBlock = '';
        if ($owner['name'])     $ownerBlock .= 'Owner: ' . esc_html($owner['name']) . '<br>';
        if ($owner['phone'])    $ownerBlock .= 'Phone: ' . esc_html($owner['phone']) . '<br>';
        if ($owner['email'])    $ownerBlock .= 'Email: ' . esc_html($owner['email']) . '<br>';
        if ($owner['address'])  $ownerBlock .= '<br><strong>Apartment address:</strong><br>' . nl2br(esc_html($owner['address'])) . '<br>';
        if ($owner['doorbell']) $ownerBlock .= 'Doorbell: ' . esc_html($owner['doorbell']) . '<br>';
        if ($ownerBlock === '') $ownerBlock  = 'Details will be provided shortly.';

        $guestNamesArr = [];
        $totalGuests = 0;

        $guestFirst = trim((string)get_post_meta($bookingId,'mphb_first_name',true));
        $guestLast  = trim((string)get_post_meta($bookingId,'mphb_last_name',true));
        if ($guestFirst || $guestLast) $guestNamesArr[] = trim($guestFirst . ' ' . $guestLast);

        if (isset($booking) && $booking) {
            try {
                $reserved = $booking->getReservedRooms();
                foreach ($reserved as $room) {
                    $totalGuests += (int)$room->getAdults() + (int)$room->getChildren();
                    $gName = trim((string)$room->getGuestName());
                    if ($gName !== '') $guestNamesArr[] = $gName;
                }
            } catch (\Throwable $e) {}
        }

        if ($totalGuests <= 0) {
            $totalGuests = (int)get_post_meta($bookingId, 'mphb_total_guests', true) ?: 1;
        }

        $allGuestNamesString = implode(', ', array_unique($guestNamesArr)) ?: 'Guest';

        $checkIn  = trim((string)get_post_meta($bookingId,'mphb_check_in_date',true));
        $checkOut = trim((string)get_post_meta($bookingId,'mphb_check_out_date',true));
        $timeIn   = get_post_meta($roomTypeId, '_sf_check_in_time', true) ?: '15:00–23:00';
        $timeOut  = get_post_meta($roomTypeId, '_sf_check_out_time', true) ?: '12:00';

        $policyType = get_post_meta($roomTypeId, '_sf_cancellation_policy', true) ?: 'non_refundable';
        $cancelDays = (int) get_post_meta($roomTypeId, '_sf_cancellation_days', true);
        
        $policyReg = get_option('stayflow_registry_policies', []);

        if ($policyType === 'free_cancellation' && $cancelDays > 0) {
            $penaltyDays = $cancelDays - 1;
            $policyRaw = $policyReg['free_cancellation'] ?? "<ul><li>Free cancellation up to <strong>{days} days before arrival</strong>.</li></ul>";
            $policyHtml = str_replace(['{days}', '{penalty_days}'], [(string)$cancelDays, (string)$penaltyDays], $policyRaw);
        } else {
            $policyHtml = $policyReg['non_refundable'] ?? "<p><strong>Non-Refundable</strong></p>";
        }

        // RU: Получаем токен. Если он пустой, значит у брони нет валидного email.
        // EN: Fetch token. If empty, the booking lacks a valid email.
        $cancelManager = new CancellationManager();
        $cancelToken = $cancelManager->generateToken($bookingId);
        
        if ($cancelToken === '') {
            $manageBookingHtml = '<div style="font-size:12px; font-weight:bold; margin-bottom:10px;">Please contact support to manage this booking.</div>';
        } else {
            $secureCancelLink = add_query_arg([
                'bid'   => $bookingId, 
                'token' => $cancelToken
            ], site_url('/manage-booking/'));
            
            $manageBookingHtml = '
                <div style="font-size:11px; margin-bottom:10px;">Change of plans? Use the secure link below:</div>
                <a href="' . esc_url($secureCancelLink) . '" class="cancel-btn">Manage / Cancel Booking</a>
            ';
        }

        $contentReg = get_option('stayflow_registry_content', []);
        $instructions = nl2br(wp_kses_post($contentReg['voucher_instructions'] ?? "Please contact your host regarding keys."));
        $contactLine = 'WhatsApp: +49 176 24615269 · E-mail: business@stay4fair.com · stay4fair.com';
        $logoUrl = 'https://stay4fair.com/wp-content/uploads/2025/12/gorizontal-color-4.png';

        ob_start(); ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body{font-family:DejaVu Sans, Arial, sans-serif;font-size:12px;color:#111;}
                .h1{font-size:20px;font-weight:800;margin:0 0 4px;}
                .box{border:1px solid #ddd;border-radius:6px;padding:10px;margin-top:10px;}
                .grid{display:table;width:100%;border-collapse:collapse;}
                .col{display:table-cell;vertical-align:top;}
                .label{font-weight:700;margin-bottom:5px;display:block;}
                .cancel-btn{display:inline-block;padding:10px 20px;background-color:#000;color:#fff !important;text-decoration:none;border-radius:4px;font-weight:bold;margin-top:10px;}
            </style>
        </head>
        <body>
            <div style="display:table;width:100%;margin-bottom:20px;">
                <div style="display:table-cell;"><img src="<?php echo esc_url($logoUrl); ?>" style="max-height:50px;"></div>
                <div style="display:table-cell;text-align:right;font-size:11px;">Stay4Fair.com<br>business@stay4fair.com</div>
            </div>

            <div class="h1">Booking Voucher</div>
            <div style="color:#666;">Voucher No: <?php echo esc_html(self::getVoucherNumber($bookingId)); ?> · ID: <?php echo (int)$bookingId; ?></div>

            <div class="grid">
                <div class="col" style="width:58%;padding-right:10px;">
                    <div class="box">
                        <span class="label">Guest</span>
                        <?php echo esc_html($allGuestNamesString); ?><br>Total: <?php echo (int)$totalGuests; ?>
                        <div style="border-top:1px solid #eee;margin:10px 0;"></div>
                        <span class="label">Stay</span>
                        Check-in: <?php echo esc_html($checkIn); ?> (from <?php echo esc_html($timeIn); ?>)<br>
                        Check-out: <?php echo esc_html($checkOut); ?> (until <?php echo esc_html($timeOut); ?>)
                    </div>
                </div>
                <div class="col" style="width:42%;">
                    <div class="box">
                        <span class="label">Apartment & Host</span>
                        <?php echo $ownerBlock; ?>
                    </div>
                </div>
            </div>

            <div class="box">
                <span class="label">Instructions</span>
                <?php echo $instructions; ?>
            </div>

            <div class="box">
                <span class="label">Cancellation Policy Details</span>
                <?php echo wp_kses_post($policyHtml); ?>
            </div>

            <div class="box" style="background-color: #f9f9f9; text-align: center; border: 1px dashed #000;">
                <span class="label">Manage your booking / Buchung verwalten</span>
                <?php echo $manageBookingHtml; ?>
            </div>

            <div style="text-align:center; margin-top:20px; font-size:10px; color:#999;">
                <?php echo esc_html($contactLine); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}