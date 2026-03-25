<?php
declare(strict_types=1);

namespace StayFlow\Integration;

use StayFlow\Booking\CancellationManager;
use StayFlow\Booking\RefundEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CancelBookingShortcode
 * @version 1.1.4 - Fixed success buttons UI
 */
final class CancelBookingShortcode
{
    private CancellationManager $cancelManager;

    public function __construct()
    {
        $this->cancelManager = new CancellationManager();
    }

    public function register(): void
    {
        add_shortcode('stayflow_cancel_booking', [$this, 'renderShortcode']);
        add_action('admin_post_stayflow_process_cancellation', [$this, 'handleCancellationSubmit']);
        add_action('admin_post_nopriv_stayflow_process_cancellation', [$this, 'handleCancellationSubmit']);
        
        add_action('admin_post_stayflow_request_dates', [$this, 'handleDateChangeSubmit']);
        add_action('admin_post_nopriv_stayflow_request_dates', [$this, 'handleDateChangeSubmit']);
    }

    // Вспомогательный метод для загрузки стилей
    private function getCommonStyles(): string {
        return '<style>
            .sf-card { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #eee; font-family: sans-serif; }
            .sf-input { width: 100%; padding: 14px; border: 1px solid #ccc; border-radius: 8px; margin-top: 5px; font-family: inherit; font-size: 15px; box-sizing: border-box; }
            
            .sf-3d-button {
                position: relative !important; overflow: hidden !important; border-radius: 10px !important; border: none !important;
                box-shadow: 0 14px 28px rgba(0,0,0,0.45), 0 4px 8px rgba(0,0,0,0.25), inset 0 -5px 10px rgba(0,0,0,0.50), inset 0 1px 0 rgba(255,255,255,0.30), inset 0 0 0 1px rgba(255,255,255,0.06) !important;
                transition: all 0.25s ease !important; cursor: pointer !important; z-index: 2; display: inline-block; width: 100%; padding: 18px; margin: 15px 0 10px;
                font-size: 16px; font-weight: bold; text-align: center; text-decoration: none; -webkit-appearance: none !important; -moz-appearance: none !important; appearance: none !important; box-sizing: border-box;
            }
            .sf-3d-button::before {
                content: "" !important; position: absolute !important; top: 2% !important; left: 6% !important; width: 88% !important; height: 55% !important;
                background: radial-gradient(ellipse at center, rgba(255,255,255,0.65) 0%, rgba(255,255,255,0.00) 72%) !important;
                transform: scaleY(0.48) !important; filter: blur(5px) !important; opacity: 0.55 !important; z-index: 1 !important; pointer-events: none !important;
            }
            .sf-btn-signature { background-color: #082567 !important; color: #E0B849 !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(0,0,0,0.15) 100%) !important; background-blend-mode: overlay; }
            .sf-btn-signature:hover { background-color: #E0B849 !important; color: #082567 !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(0,0,0,0.1) 100%) !important; transform: translateY(-2px) !important; }
            .sf-btn-red { background-color: #c62828 !important; color: #ffffff !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(0,0,0,0.15) 100%) !important; background-blend-mode: overlay; }
            .sf-btn-red:hover { background-color: #b71c1c !important; color: #ffffff !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(0,0,0,0.1) 100%) !important; transform: translateY(-2px) !important; }
        </style>';
    }

    public function renderShortcode($atts): string
    {
        $styles = $this->getCommonStyles();

        // 1. Success Cancellation View
        if (isset($_GET['cancellation_success'])) {
            return $styles . '
            <div class="sf-card" style="max-width: 550px; margin: 40px auto; text-align:center; border-top: 6px solid #c62828;">
                <h2 style="color: #c62828; margin-top:0;">Booking Cancelled</h2>
                <p style="font-size: 16px; color: #555;">Your booking has been successfully cancelled.</p>
                <p style="font-size: 14px; color: #777;">If applicable, refunds will be processed according to your cancellation policy to your original payment method.</p>
                <a href="'.esc_url(site_url()).'" class="sf-3d-button sf-btn-signature" style="margin-top:25px; width: 80%;">Return to Home</a>
            </div>';
        }

        // 2. Success Date Change View
        if (isset($_GET['success_change'])) {
            return $styles . '
            <div class="sf-card" style="max-width: 550px; margin: 40px auto; text-align:center; border-top: 6px solid #082567;">
                <h2 style="color: #082567; margin-top:0;">Request Sent!</h2>
                <p style="font-size: 16px; color: #555;">We have received your request to change dates.</p>
                <p style="font-size: 14px; color: #777;">Our team will contact you shortly to confirm the new schedule.</p>
                <a href="'.esc_url(site_url()).'" class="sf-3d-button sf-btn-signature" style="margin-top:25px; width: 80%;">Return to Home</a>
            </div>';
        }

        // Загрузка формы (Form Load)
        $bookingId = isset($_GET['bid']) ? (int)$_GET['bid'] : 0;
        $token     = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $email     = isset($_GET['e']) ? sanitize_email($_GET['e']) : '';

        if (!$bookingId || !$this->cancelManager->validateToken($token, $bookingId, $email)) {
            return '<div style="padding:20px; color:red; text-align:center; background:#fff; border-radius:8px;">Invalid security token or booking ID.</div>';
        }

        ob_start();
        echo $styles;
        try {
            $status = $this->cancelManager->getCancellationStatus($bookingId);
            $this->renderUI($bookingId, $email, $token, $status);
        } catch (\Exception $e) {
            echo '<div style="padding:20px; color:red; text-align:center;">Error: ' . esc_html($e->getMessage()) . '</div>';
        }
        return ob_get_clean();
    }

    private function renderUI(int $bookingId, string $email, string $token, array $status): void
    {
        $isFree = $status['is_free'];

        echo '<div class="sf-card" style="max-width: 550px; margin: 40px auto;">';
        echo '<h2 style="text-align:center; margin-top:0; color:#333;">Manage Booking #'.$bookingId.'</h2>';

        if ($isFree) {
            echo '<div style="background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px; border-radius: 4px; margin-bottom: 25px; color: #2e7d32;">
                <strong style="font-size:16px;">Free cancellation available!</strong><br>Deadline: '.date('d. M Y', strtotime($status['deadline_date'])).'
            </div>';
        } else {
            echo '<div style="background: #ffebee; border-left: 4px solid #c62828; padding: 15px; border-radius: 4px; margin-bottom: 25px; color: #c62828;">
                <strong style="font-size:16px;">Non-refundable period.</strong><br>Cancellation will result in a 100% penalty.
            </div>';
        }

        echo '<form action="'.admin_url('admin-post.php').'" method="POST" style="margin-bottom: 35px;">';
        echo '<input type="hidden" name="action" value="stayflow_process_cancellation">';
        echo '<input type="hidden" name="bid" value="'.$bookingId.'">';
        echo '<input type="hidden" name="e" value="'.$email.'">';
        echo '<input type="hidden" name="token" value="'.$token.'">';
        wp_nonce_field('sf_cancel_'.$bookingId);

        echo '<label style="font-weight:bold; color:#555; display:block;">Reason for cancellation (optional):</label>';
        echo '<select name="cancel_reason" class="sf-input">';
        echo '<option value="">-- Select reason --</option>';
        echo '<option value="Change of plans">Change of plans</option>';
        echo '<option value="Travel restrictions">Travel restrictions</option>';
        echo '<option value="Personal reasons">Personal reasons</option>';
        echo '<option value="Other">Other</option>';
        echo '</select>';

        $btnClass = $isFree ? 'sf-btn-signature' : 'sf-btn-red';
        $btnText = $isFree ? 'Confirm Free Cancellation' : 'Cancel with 100% Penalty';
        echo '<button type="submit" class="sf-3d-button '.$btnClass.'" onclick="return confirm(\'Are you sure you want to cancel?\');">'.$btnText.'</button>';
        echo '</form>';

        echo '<hr style="border: 0; border-top: 1px solid #ddd; margin: 30px 0;">';

        echo '<form action="'.admin_url('admin-post.php').'" method="POST">';
        echo '<input type="hidden" name="action" value="stayflow_request_dates">';
        echo '<input type="hidden" name="bid" value="'.$bookingId.'">';
        echo '<input type="hidden" name="e" value="'.$email.'">';
        echo '<input type="hidden" name="token" value="'.$token.'">';
        wp_nonce_field('sf_change_'.$bookingId);

        echo '<h3 style="margin-top:0; margin-bottom:10px; color:#082567;">Request Date Change</h3>';
        echo '<p style="font-size:14px; color:#666; margin-bottom:15px;">Want to move your stay? Send us a request with your preferred dates.</p>';
        echo '<textarea name="date_comment" class="sf-input" placeholder="Example: I want to move my check-in from March 25 to March 27." rows="4" required></textarea>';
        echo '<button type="submit" class="sf-3d-button sf-btn-signature">Request New Dates</button>';
        echo '</form>';

        echo '</div>';
    }

    public function handleCancellationSubmit(): void {
        $bookingId = (int)$_POST['bid'];
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sf_cancel_'.$bookingId)) wp_die('Security check failed.');
        
        $status = $this->cancelManager->getCancellationStatus($bookingId);
        $engine = new RefundEngine();
        $result = $engine->processCancellation($bookingId, sanitize_text_field($_POST['cancel_reason'] ?? ''), $status['is_free']);
        
        if (!$result['success']) wp_die('System Error: ' . esc_html($result['message']));
        
        wp_redirect(add_query_arg('cancellation_success', '1', wp_get_referer()));
        exit;
    }

    public function handleDateChangeSubmit(): void {
        $bookingId = (int)$_POST['bid'];
        $email = sanitize_email($_POST['e']);
        $comment = sanitize_textarea_field($_POST['date_comment']);

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sf_change_'.$bookingId)) wp_die('Security check failed.');
        if (!$this->cancelManager->validateToken($_POST['token'], $bookingId, $email)) wp_die('Security error.');

        do_action('stayflow_request_date_change_notify', $bookingId, $email, $comment);
        wp_redirect(add_query_arg('success_change', '1', wp_get_referer()));
        exit;
    }
}