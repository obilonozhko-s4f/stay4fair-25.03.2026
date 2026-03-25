<?php

declare(strict_types=1);

namespace StayFlow\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.0.0
 * RU: Провайдер глобального уведомления (Launch Popup / Maintenance).
 * EN: Global site notice provider (Launch Popup / Maintenance).
 */
final class SiteNoticeProvider
{
    public function register(): void
    {
        // RU: Выводим попап в футере на всех страницах фронтенда
        // EN: Output the popup in the footer on all frontend pages
        add_action('wp_footer', [$this, 'renderPopup']);
    }

    public function renderPopup(): void
    {
        // RU: Не выводим в админке
        if (is_admin()) return;

        $options = get_option('stayflow_site_notice_settings', []);
        
        // RU: Если попап выключен - ничего не делаем
        if (empty($options['enabled'])) {
            return;
        }

        $logo_url = !empty($options['logo_url']) ? $options['logo_url'] : '';
        $cookie_days = !empty($options['cookie_days']) ? (int)$options['cookie_days'] : 1;
        $content = !empty($options['content']) ? $options['content'] : '';

        if (empty($content)) return; // Не выводим пустой попап

        ob_start();
        ?>
        <style>
            /* RU: Стили глобального попапа (Glassmorphism) */
            .sf-global-notice-overlay {
                position: fixed;
                top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(8, 37, 103, 0.4);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                z-index: 999999;
                display: flex;
                justify-content: center;
                align-items: center;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.4s ease, visibility 0.4s ease;
            }
            .sf-global-notice-overlay.active {
                opacity: 1;
                visibility: visible;
            }
            .sf-global-notice-modal {
                background: #ffffff;
                border: 2px solid #E0B849;
                border-radius: 24px;
                padding: 40px;
                width: 90%;
                max-width: 550px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                position: relative;
                transform: translateY(20px) scale(0.95);
                transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                max-height: 90vh;
                overflow-y: auto;
            }
            .sf-global-notice-overlay.active .sf-global-notice-modal {
                transform: translateY(0) scale(1);
            }
            .sf-global-notice-close {
                position: absolute;
                top: 15px;
                right: 15px;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: transparent;
                border: none;
                color: #64748b;
                font-size: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: 0.2s;
                line-height: 1;
            }
            .sf-global-notice-close:hover {
                background: #f1f5f9;
                color: #082567;
            }
            .sf-global-notice-logo {
                text-align: center;
                margin-bottom: 25px;
            }
            .sf-global-notice-logo img {
                max-height: 50px;
                width: auto;
            }
            .sf-global-notice-content {
                font-family: 'Segoe UI', Roboto, sans-serif;
                color: #334155;
                font-size: 16px;
                line-height: 1.6;
            }
        </style>

        <div id="sf-global-notice" class="sf-global-notice-overlay" data-days="<?php echo esc_attr((string)$cookie_days); ?>">
            <div class="sf-global-notice-modal">
                <button class="sf-global-notice-close" aria-label="Close">×</button>
                
                <?php if ($logo_url): ?>
                <div class="sf-global-notice-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Notice">
                </div>
                <?php endif; ?>

                <div class="sf-global-notice-content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var overlay = document.getElementById('sf-global-notice');
                if (!overlay) return;
                
                var cookieDays = parseInt(overlay.getAttribute('data-days')) || 1;
                var cacheKey = 'sf_notice_dismissed_v1'; // Change v-number to reset cache for all users
                var dismissUntil = localStorage.getItem(cacheKey);
                var now = new Date().getTime();

                // RU: Если кэша нет или он истек - показываем попап
                if (!dismissUntil || now > parseInt(dismissUntil)) {
                    setTimeout(function() {
                        overlay.classList.add('active');
                    }, 800); // Небольшая задержка для плавности при загрузке страницы
                }

                function closeNotice() {
                    overlay.classList.remove('active');
                    // RU: Устанавливаем время, до которого попап не будет показываться
                    var expireTime = now + (cookieDays * 24 * 60 * 60 * 1000);
                    localStorage.setItem(cacheKey, expireTime);
                }

                // RU: Закрытие по крестику
                overlay.querySelector('.sf-global-notice-close').addEventListener('click', closeNotice);
                
                // RU: Закрытие по клику мимо модального окна ("по стеклу")
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeNotice();
                    }
                });
            });
        </script>
        <?php
        echo ob_get_clean();
    }
}
