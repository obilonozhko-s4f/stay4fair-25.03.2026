<?php

declare(strict_types=1);

namespace StayFlow\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.1.0
 * RU: Провайдер Центра Поддержки Владельцев. 
 * - [FIX]: Мобильная верстка кнопок (выстраиваются в колонку).
 * - [UPDATE]: Динамический номер WhatsApp (настройки -> фоллбэк на админа).
 * EN: Owner Support Center provider. Mobile buttons fix and dynamic WA number logic.
 */
final class SupportProvider
{
    public function register(): void
    {
        add_shortcode('sf_owner_support', [$this, 'renderForm']);
        add_action('wp_ajax_sf_owner_support_send', [$this, 'handleEmailRequest']);
    }

    public function renderForm(): string
    {
        if (!is_user_logged_in()) {
            return '<p>Bitte loggen Sie sich ein.</p>';
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        
        // RU: Получаем все квартиры владельца
        global $wpdb;
        $apt_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'bsbt_owner_id'
            WHERE p.post_type = 'mphb_room_type' 
            AND p.post_status != 'trash'
            AND (p.post_author = %d OR pm.meta_value = %d)
        ", $userId, $userId));

        $apartments = [];
        if (!empty($apt_ids)) {
            $apartments = get_posts([
                'post_type'      => 'mphb_room_type',
                'post__in'       => $apt_ids,
                'posts_per_page' => -1,
                'post_status'    => 'any'
            ]);
        }

        $nonce = wp_create_nonce('sf_support_nonce');
        $ajax_url = admin_url('admin-ajax.php');

        // ==========================================================================
        // RU: ЛОГИКА НОМЕРА WHATSAPP (DYNAMIC NUMBER RESOLUTION)
        // ==========================================================================
        $settings = get_option('stayflow_core_settings', []);
        $wa_phone = !empty($settings['support_phone']) ? preg_replace('/[^0-9]/', '', $settings['support_phone']) : '';
        
        // RU: Если номер не задан, ищем главного админа
        if (empty($wa_phone)) {
            $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
            if (!empty($admin_users)) {
                $admin_id = $admin_users[0]->ID;
                $phone = get_user_meta($admin_id, 'bsbt_phone', true);
                if (empty($phone)) {
                    $phone = get_user_meta($admin_id, 'billing_phone', true); // Запасной вариант
                }
                $wa_phone = preg_replace('/[^0-9]/', '', (string)$phone);
            }
        }

        ob_start();
        ?>
        <style>
            .sf-support-container { font-family: 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px 0; }
            .sf-support-header { border-bottom: 2px solid #E0B849; padding-bottom: 15px; margin-bottom: 25px; }
            .sf-support-header h2 { color: #082567; margin: 0; font-size: 28px; font-weight: 800; }
            .sf-support-header p { color: #64748b; margin: 10px 0 0 0; font-size: 15px; }

            .sf-support-glass-card { background: rgba(255, 255, 255, 0.8); border: 1px solid #e2e8f0; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(8, 37, 103, 0.05); margin-bottom: 30px; }
            
            .sf-apt-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; margin: 15px 0 25px 0; }
            .sf-apt-checkbox-item { display: flex; align-items: flex-start; gap: 10px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px; border-radius: 10px; cursor: pointer; transition: 0.2s; }
            .sf-apt-checkbox-item:hover { border-color: #E0B849; background: #fdf8ed; }
            .sf-apt-checkbox-item input { margin-top: 3px; }
            .sf-apt-checkbox-item strong { color: #082567; font-size: 14px; display: block; margin-bottom: 2px; }
            .sf-apt-checkbox-item span { color: #64748b; font-size: 11px; }

            .sf-support-textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 15px; font-family: inherit; font-size: 14px; color: #1e293b; resize: vertical; min-height: 120px; box-sizing: border-box; background: #fff; outline: none; transition: 0.2s; }
            .sf-support-textarea:focus { border-color: #082567; box-shadow: 0 0 0 2px rgba(8, 37, 103, 0.1); }

            .sf-support-actions { display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap; }
            
            /* ==========================================================================
               TOTAL 3D VOLUME SYSTEM BUTTONS
               ========================================================================== */
            .sf-3d-btn {
                position: relative !important; overflow: hidden !important; border-radius: 10px !important; border: none !important;
                box-shadow: 0 14px 28px rgba(0,0,0,0.45), 0 4px 8px rgba(0,0,0,0.25), inset 0 -5px 10px rgba(0,0,0,0.50), inset 0 1px 0 rgba(255,255,255,0.30), inset 0 0 0 1px rgba(255,255,255,0.06) !important;
                transition: all 0.25s ease !important; cursor: pointer !important; z-index: 2; display: inline-flex;
                align-items: center; justify-content: center; padding: 14px 28px; font-family: 'Segoe UI', Roboto, sans-serif;
                font-weight: 700; font-size: 15px; text-decoration: none !important; -webkit-appearance: none !important; flex: 1;
            }
            .sf-3d-btn::before {
                content: "" !important; position: absolute !important; top: 2% !important; left: 6% !important; width: 88% !important; height: 55% !important;
                background: radial-gradient(ellipse at center, rgba(255,255,255,0.65) 0%, rgba(255,255,255,0.00) 72%) !important;
                transform: scaleY(0.48) !important; filter: blur(5px) !important; opacity: 0.55 !important; z-index: 1 !important; pointer-events: none !important;
            }
            .sf-3d-btn:hover { transform: translateY(-2px) !important; }
            .sf-3d-btn span { position: relative; z-index: 3; display: flex; align-items: center; gap: 8px; }

            .btn-email { background-color: #082567 !important; color: #E0B849 !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(0,0,0,0.15) 100%) !important; background-blend-mode: overlay; }
            .btn-email:hover { background-color: #E0B849 !important; color: #082567 !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(0,0,0,0.1) 100%) !important; }
            
            .btn-wa { background-color: #25D366 !important; color: #ffffff !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(0,0,0,0.15) 100%) !important; background-blend-mode: overlay; }
            .btn-wa:hover { background-color: #128C7E !important; background-image: linear-gradient(180deg, rgba(255,255,255,0.3) 0%, rgba(0,0,0,0.1) 100%) !important; }

            /* ==========================================================================
               MOBILE RESPONSIVENESS (Кнопки друг под другом)
               ========================================================================== */
            @media (max-width: 768px) {
                .sf-support-actions { flex-direction: column; gap: 12px; }
                .sf-support-actions .sf-3d-btn { width: 100%; box-sizing: border-box; }
            }
        </style>

        <div class="sf-support-container">
            <div class="sf-support-header">
                <h2>Support & Hilfe</h2>
                <p>Haben Sie Fragen oder benötigen Sie Unterstützung? Unser Team ist für Sie da.</p>
            </div>

            <div class="sf-support-glass-card">
                <h3 style="color: #082567; margin: 0 0 5px 0;">1. Worum geht es?</h3>
                <p style="color: #64748b; font-size: 13px; margin: 0 0 15px 0;">Wählen Sie die betreffenden Apartments aus (optional):</p>
                
                <div class="sf-apt-list">
                    <?php if (empty($apartments)): ?>
                        <div style="color: #94a3b8; font-style: italic;">Keine Apartments gefunden.</div>
                    <?php else: ?>
                        <?php foreach ($apartments as $apt): ?>
                            <label class="sf-apt-checkbox-item">
                                <input type="checkbox" class="sf-apt-cb" value="<?php echo esc_attr($apt->post_title); ?>" data-id="<?php echo esc_attr((string)$apt->ID); ?>">
                                <div>
                                    <strong><?php echo esc_html($apt->post_title); ?></strong>
                                    <span>ID: #<?php echo esc_html((string)$apt->ID); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h3 style="color: #082567; margin: 25px 0 10px 0;">2. Ihre Nachricht</h3>
                <textarea id="sf-support-msg" class="sf-support-textarea" placeholder="Beschreiben Sie Ihr Anliegen hier..."></textarea>

                <div class="sf-support-actions">
                    <button type="button" class="sf-3d-btn btn-email" id="sf-btn-email">
                        <span>✉️ Nachricht senden (E-Mail)</span>
                    </button>
                    <button type="button" class="sf-3d-btn btn-wa" id="sf-btn-wa">
                        <span>💬 via WhatsApp klären</span>
                    </button>
                </div>
                <div id="sf-support-feedback" style="margin-top: 15px; font-weight: bold; text-align: center; display: none;"></div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo home_url('/owner-dashboard/'); ?>" class="sf-3d-btn btn-email" style="display: inline-block; flex: none;">
                    <span>← Zurück zum Dashboard</span>
                </a>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const btnWa = document.getElementById('sf-btn-wa');
                const btnEmail = document.getElementById('sf-btn-email');
                const msgBox = document.getElementById('sf-support-msg');
                const feedback = document.getElementById('sf-support-feedback');
                
                const ownerName = "<?php echo esc_js($user->display_name); ?>";
                const waPhone = "<?php echo esc_js($wa_phone); ?>";

                function getSelectedApts() {
                    let apts = [];
                    document.querySelectorAll('.sf-apt-cb:checked').forEach(cb => {
                        apts.push(`${cb.value} (ID: #${cb.getAttribute('data-id')})`);
                    });
                    return apts;
                }

                // ==========================================
                // WHATSAPP LOGIC
                // ==========================================
                btnWa.addEventListener('click', function() {
                    if (!waPhone) {
                        alert('Hinweis: Es ist derzeit keine WhatsApp-Nummer im System hinterlegt. Bitte nutzen Sie E-Mail.');
                        return;
                    }

                    let apts = getSelectedApts();
                    let msg = msgBox.value.trim();
                    
                    let text = `Hallo Stay4Fair Team!\nIch bin ${ownerName}.\n\n`;
                    if (apts.length > 0) {
                        text += `Meine Frage betrifft folgende Objekte:\n- ${apts.join('\n- ')}\n\n`;
                    }
                    if (msg) {
                        text += `Nachricht:\n${msg}`;
                    } else {
                        text += `Ich habe eine Frage zu...`;
                    }

                    let waUrl = `https://wa.me/${waPhone}?text=${encodeURIComponent(text)}`;
                    window.open(waUrl, '_blank');
                });

                // ==========================================
                // EMAIL (AJAX) LOGIC
                // ==========================================
                btnEmail.addEventListener('click', function() {
                    let msg = msgBox.value.trim();
                    let apts = getSelectedApts();

                    if (!msg && apts.length === 0) {
                        alert('Bitte wählen Sie ein Apartment aus oder schreiben Sie eine Nachricht.');
                        return;
                    }

                    const originalText = btnEmail.innerHTML;
                    btnEmail.innerHTML = '<span>⏳ Wird gesendet...</span>';
                    btnEmail.disabled = true;

                    const formData = new URLSearchParams();
                    formData.append('action', 'sf_owner_support_send');
                    formData.append('_wpnonce', '<?php echo esc_js($nonce); ?>');
                    formData.append('message', msg);
                    formData.append('apartments', JSON.stringify(apts));

                    fetch('<?php echo esc_js($ajax_url); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            msgBox.value = '';
                            document.querySelectorAll('.sf-apt-cb').forEach(cb => cb.checked = false);
                            feedback.style.color = '#25D366';
                            feedback.innerText = 'Ihre Nachricht wurde erfolgreich gesendet!';
                        } else {
                            feedback.style.color = '#ef4444';
                            feedback.innerText = 'Fehler: ' + (data.data?.message || 'Unbekannter Fehler');
                        }
                        feedback.style.display = 'block';
                    })
                    .catch(err => {
                        feedback.style.color = '#ef4444';
                        feedback.innerText = 'Ein Systemfehler ist aufgetreten.';
                        feedback.style.display = 'block';
                    })
                    .finally(() => {
                        btnEmail.innerHTML = originalText;
                        btnEmail.disabled = false;
                        setTimeout(() => { feedback.style.display = 'none'; }, 5000);
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    // ==========================================================================
    // AJAX HANDLER (EMAIL)
    // ==========================================================================
    public function handleEmailRequest(): void
    {
        check_ajax_referer('sf_support_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Bitte loggen Sie sich ein.']);
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        
        $message_raw = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $apts_raw = isset($_POST['apartments']) ? json_decode(stripslashes($_POST['apartments']), true) : [];
        
        $apts = is_array($apts_raw) ? array_map('sanitize_text_field', $apts_raw) : [];

        // RU: Получаем Email саппорта из настроек, фоллбэк на админский
        $settings = get_option('stayflow_core_settings', []);
        $to_email = !empty($settings['support_email']) ? sanitize_email($settings['support_email']) : get_option('admin_email');

        $subject = "[Support Ticket] Frage von {$user->display_name}";

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; color: #1d2327; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;">
            <h2 style="color: #082567; border-bottom: 2px solid #E0B849; padding-bottom: 10px;">Neues Support Ticket</h2>
            
            <p><strong>Vermieter:</strong> <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</p>
            
            <?php if (!empty($apts)): ?>
                <div style="background: #f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-radius: 8px; margin: 15px 0;">
                    <strong>Betroffene Objekte:</strong>
                    <ul style="margin-top: 10px; margin-bottom: 0;">
                        <?php foreach ($apts as $apt): ?>
                            <li><?php echo esc_html($apt); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($message_raw)): ?>
                <div style="background: #fdf8ed; padding: 15px; border: 1px solid #E0B849; border-radius: 8px; margin: 15px 0;">
                    <strong>Nachricht:</strong><br><br>
                    <?php echo nl2br(esc_html($message_raw)); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $email_body = ob_get_clean();

        // RU: Ставим Reply-To на владельца
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $user->display_name . ' <' . $user->user_email . '>'
        ];

        $sent = wp_mail($to_email, $subject, $email_body, $headers);

        if ($sent) {
            wp_send_json_success(['message' => 'Gesendet']);
        } else {
            wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden.']);
        }
    }
}
