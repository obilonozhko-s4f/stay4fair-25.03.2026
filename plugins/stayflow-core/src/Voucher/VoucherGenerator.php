<?php

declare(strict_types=1);

namespace StayFlow\Voucher;

use StayFlow\Booking\CancellationManager;
use StayFlow\Support\PdfEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.5.1
 *
 * RU:
 * Security hardening & Refactoring:
 * - Убран remote logo URL из PDF HTML (используется локальный путь из PdfEngine).
 * - Логика рендеринга PDF вынесена в централизованный StayFlow\Support\PdfEngine (DRY).
 * - Добавлена базовая защита директории vouchers (.htaccess + index.html).
 * - Улучшена генерация имени файла.
 * - Сохранен текущий flow PAIDEMAIL / voucher cache.
 * + Фикс v1.5.1: Разделение путей логотипа для PDF (локальный) и для Email (публичный URL).
 */
final class VoucherGenerator
{
    private const BS_EXT_REF_META = '_bs_external_reservation_ref';

    // ==========================================
    // RU: Вспомогательные методы / EN: Helpers
    // ==========================================

    public static function getVoucherNumber(int $bookingId): string
    {
        if (function_exists('bsbt_get_display_booking_ref')) {
            return (string) bsbt_get_display_booking_ref($bookingId);
        }

        $ext = trim((string) get_post_meta($bookingId, self::BS_EXT_REF_META, true));
        if ($ext !== '') {
            return $ext;
        }

        $candidateKeys = [
            'bs_external_reservation',
            'external_reservation_number',
            'bs_booking_number',
            'reservation_number',
        ];

        foreach ($candidateKeys as $key) {
            $val = trim((string) get_post_meta($bookingId, $key, true));
            if ($val !== '') {
                return $val;
            }
        }

        $internal = trim((string) get_post_meta($bookingId, 'bs_internal_booking_number', true));
        if ($internal !== '') {
            return $internal;
        }

        return (string) $bookingId;
    }

    // ==========================================
    // RU: Генерация PDF / EN: PDF Generation
    // ==========================================

    public static function generatePdfFile(int $bookingId, string $suffix = ''): string
    {
        if ($bookingId <= 0) {
            return '';
        }

        // КРИТИЧНО: Передаем true, так как собираем HTML именно для PDF
        $html = self::renderHtml($bookingId, true);
        
        if ($html === '') {
            return '';
        }

        $dir = self::getVoucherDir();
        if ($dir === '') {
            return '';
        }

        self::createDirectoryProtectionFiles($dir);

        $file = self::buildVoucherFilePath($dir, $bookingId, $suffix);

        // RU: Сохраняем старую логику кеширования paid email PDF.
        if ($suffix === 'PAIDEMAIL' && is_file($file) && filesize($file) > 800) {
            return $file;
        }

        try {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', '300');

            // Используем безопасный централизованный движок
            PdfEngine::save($html, $file);

        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[StayFlow VoucherGenerator Error] ' . $e->getMessage());
            }
            return '';
        }

        return (is_file($file) && filesize($file) > 800) ? $file : '';
    }

    private static function getVoucherDir(): string
    {
        $uploadDir = wp_upload_dir();
        if (empty($uploadDir['basedir']) || !is_string($uploadDir['basedir'])) {
            return '';
        }

        $dir = trailingslashit($uploadDir['basedir']) . 'bs-vouchers';

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return '';
        }

        return $dir;
    }

    private static function buildVoucherFilePath(string $dir, int $bookingId, string $suffix = ''): string
    {
        $bookingId = absint($bookingId);

        if ($suffix !== '') {
            $safeSuffix = preg_replace('/[^A-Za-z0-9_\-]/', '', $suffix);
            if (!is_string($safeSuffix) || $safeSuffix === '') {
                $safeSuffix = 'FILE';
            }

            $name = 'Voucher-' . $bookingId . '-' . $safeSuffix . '.pdf';
            return trailingslashit($dir) . $name;
        }

        $random = function_exists('wp_generate_password')
            ? wp_generate_password(10, false, false)
            : substr(md5((string) wp_rand()), 0, 10);

        $name = sprintf(
            'Voucher-%d-%s-%s.pdf',
            $bookingId,
            wp_date('Ymd-His'),
            strtolower($random)
        );

        return trailingslashit($dir) . $name;
    }

    private static function createDirectoryProtectionFiles(string $dir): void
    {
        $indexFile = trailingslashit($dir) . 'index.html';
        if (!is_file($indexFile)) {
            @file_put_contents($indexFile, '', LOCK_EX);
        }

        $htaccessFile = trailingslashit($dir) . '.htaccess';
        if (!is_file($htaccessFile)) {
            $rules = <<<HTACCESS
Options -Indexes
<FilesMatch "\.(pdf)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

HTACCESS;
            @file_put_contents($htaccessFile, $rules, LOCK_EX);
        }
    }

    // ==========================================
    // RU: Рендеринг HTML / EN: HTML Rendering
    // ==========================================

    /**
     * @param bool $forPdf Если true — используем локальный путь (chroot). Если false — публичный URL (для Email).
     */
    public static function renderHtml(int $bookingId, bool $forPdf = false): string
    {
        $owner = [
            'name'     => '',
            'phone'    => '',
            'email'    => '',
            'address'  => '',
            'doorbell' => '',
        ];

        $roomTypeId = 0;
        $booking = null;

        if (function_exists('MPHB')) {
            try {
                $booking = \MPHB()->getBookingRepository()->findById($bookingId);
                if ($booking) {
                    $reserved = $booking->getReservedRooms();
                    if (!empty($reserved)) {
                        $first = reset($reserved);
                        $roomTypeId = (int) $first->getRoomTypeId();

                        if ($roomTypeId > 0) {
                            $owner['name']     = trim((string) get_post_meta($roomTypeId, 'owner_name', true));
                            $owner['phone']    = trim((string) get_post_meta($roomTypeId, 'owner_phone', true));
                            $owner['email']    = trim((string) get_post_meta($roomTypeId, 'owner_email', true));
                            $owner['address']  = trim((string) get_post_meta($roomTypeId, 'address', true));
                            $owner['doorbell'] = trim((string) get_post_meta($roomTypeId, 'doorbell_name', true));
                        }
                    }
                }
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[StayFlow VoucherGenerator Booking Read Error] ' . $e->getMessage());
                }
            }
        }

        $ownerBlock = '';
        if ($owner['name'] !== '') {
            $ownerBlock .= 'Owner: ' . esc_html($owner['name']) . '<br>';
        }
        if ($owner['phone'] !== '') {
            $ownerBlock .= 'Phone: ' . esc_html($owner['phone']) . '<br>';
        }
        if ($owner['email'] !== '') {
            $ownerBlock .= 'Email: ' . esc_html($owner['email']) . '<br>';
        }
        if ($owner['address'] !== '') {
            $ownerBlock .= '<br><strong>Apartment address:</strong><br>' . nl2br(esc_html($owner['address'])) . '<br>';
        }
        if ($owner['doorbell'] !== '') {
            $ownerBlock .= 'Doorbell: ' . esc_html($owner['doorbell']) . '<br>';
        }
        if ($ownerBlock === '') {
            $ownerBlock = 'Details will be provided shortly.';
        }

        $guestNamesArr = [];
        $totalGuests   = 0;

        $guestFirst = trim((string) get_post_meta($bookingId, 'mphb_first_name', true));
        $guestLast  = trim((string) get_post_meta($bookingId, 'mphb_last_name', true));
        if ($guestFirst !== '' || $guestLast !== '') {
            $guestNamesArr[] = trim($guestFirst . ' ' . $guestLast);
        }

        if ($booking) {
            try {
                $reserved = $booking->getReservedRooms();
                foreach ($reserved as $room) {
                    $totalGuests += (int) $room->getAdults() + (int) $room->getChildren();

                    $gName = trim((string) $room->getGuestName());
                    if ($gName !== '') {
                        $guestNamesArr[] = $gName;
                    }
                }
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[StayFlow VoucherGenerator Guest Read Error] ' . $e->getMessage());
                }
            }
        }

        if ($totalGuests <= 0) {
            $totalGuests = (int) get_post_meta($bookingId, 'mphb_total_guests', true);
            if ($totalGuests <= 0) {
                $totalGuests = 1;
            }
        }

        $allGuestNamesString = implode(', ', array_unique(array_filter($guestNamesArr))) ?: 'Guest';

        $checkIn  = trim((string) get_post_meta($bookingId, 'mphb_check_in_date', true));
        $checkOut = trim((string) get_post_meta($bookingId, 'mphb_check_out_date', true));

        $timeIn  = trim((string) get_post_meta($roomTypeId, '_sf_check_in_time', true));
        $timeOut = trim((string) get_post_meta($roomTypeId, '_sf_check_out_time', true));

        if ($timeIn === '') {
            $timeIn = '15:00–23:00';
        }
        if ($timeOut === '') {
            $timeOut = '12:00';
        }

        $policyType = trim((string) get_post_meta($roomTypeId, '_sf_cancellation_policy', true));
        if ($policyType === '') {
            $policyType = 'non_refundable';
        }

        $cancelDays = (int) get_post_meta($roomTypeId, '_sf_cancellation_days', true);
        $policyReg  = get_option('stayflow_registry_policies', []);

        if ($policyType === 'free_cancellation' && $cancelDays > 0) {
            $penaltyDays = max(0, $cancelDays - 1);
            $policyRaw   = is_array($policyReg)
                ? (string) ($policyReg['free_cancellation'] ?? '<ul><li>Free cancellation up to <strong>{days} days before arrival</strong>.</li></ul>')
                : '<ul><li>Free cancellation up to <strong>{days} days before arrival</strong>.</li></ul>';

            $policyHtml = str_replace(
                ['{days}', '{penalty_days}'],
                [(string) $cancelDays, (string) $penaltyDays],
                $policyRaw
            );
        } else {
            $policyHtml = is_array($policyReg)
                ? (string) ($policyReg['non_refundable'] ?? '<p><strong>Non-Refundable</strong></p>')
                : '<p><strong>Non-Refundable</strong></p>';
        }

        $cancelManager = new CancellationManager();
        $cancelToken   = $cancelManager->generateToken($bookingId);

        if ($cancelToken === '') {
            $manageBookingHtml = '<div style="font-size:12px; font-weight:bold; margin-bottom:10px;">Please contact support to manage this booking.</div>';
        } else {
            $secureCancelLink = add_query_arg(
                [
                    'bid'   => $bookingId,
                    'token' => $cancelToken,
                ],
                site_url('/manage-booking/')
            );

            $manageBookingHtml = '
                <div style="font-size:11px; margin-bottom:10px;">Change of plans? Use the secure link below:</div>
                <a href="' . esc_url($secureCancelLink) . '" class="cancel-btn">Manage / Cancel Booking</a>
            ';
        }

        $contentReg = get_option('stayflow_registry_content', []);
        $instructionsRaw = is_array($contentReg)
            ? (string) ($contentReg['voucher_instructions'] ?? 'Please contact your host regarding keys.')
            : 'Please contact your host regarding keys.';

        $instructions = nl2br(wp_kses_post($instructionsRaw));
        $contactLine  = 'WhatsApp: +49 176 24615269 · E-mail: business@stay4fair.com · stay4fair.com';
        
        // RU: Умная отдача логотипа.
        if ($forPdf) {
            $logoSrc = PdfEngine::logoPath();
        } else {
            $uploadDir = wp_upload_dir();
            $baseurl   = is_array($uploadDir) && !empty($uploadDir['baseurl']) ? $uploadDir['baseurl'] : site_url('/wp-content/uploads');
            $logoSrc   = trailingslashit($baseurl) . '2025/12/gorizontal-color-4.png';
        }

        ob_start();
        ?>
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
                <div style="display:table-cell;">
                    <?php if ($logoSrc !== '') : ?>
                        <img src="<?php echo esc_attr($logoSrc); ?>" style="max-height:50px;">
                    <?php endif; ?>
                </div>
                <div style="display:table-cell;text-align:right;font-size:11px;">Stay4Fair.com<br>business@stay4fair.com</div>
            </div>

            <div class="h1">Booking Voucher</div>
            <div style="color:#666;">Voucher No: <?php echo esc_html(self::getVoucherNumber($bookingId)); ?> · ID: <?php echo (int) $bookingId; ?></div>

            <div class="grid">
                <div class="col" style="width:58%;padding-right:10px;">
                    <div class="box">
                        <span class="label">Guest</span>
                        <?php echo esc_html($allGuestNamesString); ?><br>Total: <?php echo (int) $totalGuests; ?>
                        <div style="border-top:1px solid #eee;margin:10px 0;"></div>
                        <span class="label">Stay</span>
                        Check-in: <?php echo esc_html($checkIn); ?> (from <?php echo esc_html($timeIn); ?>)<br>
                        Check-out: <?php echo esc_html($checkOut); ?> (until <?php echo esc_html($timeOut); ?>)
                    </div>
                </div>
                <div class="col" style="width:42%;">
                    <div class="box">
                        <span class="label">Apartment &amp; Host</span>
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

            <div class="box" style="background-color:#f9f9f9; text-align:center; border:1px dashed #000;">
                <span class="label">Manage your booking / Buchung verwalten</span>
                <?php echo $manageBookingHtml; ?>
            </div>

            <div style="text-align:center; margin-top:20px; font-size:10px; color:#999;">
                <?php echo esc_html($contactLine); ?>
            </div>
        </body>
        </html>
        <?php

        $html = (string) ob_get_clean();
        return trim($html);
    }
}
