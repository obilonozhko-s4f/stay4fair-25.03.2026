<?php

declare(strict_types=1);

namespace StayFlow\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.1.0
 * RU: Провайдер глобального уведомления (Launch Popup). Стилизация Liquid Glass (Glassmorphism).
 * EN: Global site notice provider (Launch Popup). Liquid Glass (Glassmorphism) styling.
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
        // RU: Не выводим в админке / EN: Do not output in admin panel
        if (is_admin()) return;

        $options = get_option('stayflow_site_notice_settings', []);
        
        // RU: Если попап выключен - ничего не делаем
        if (empty($options['enabled'])) {
            return;
        }

        $logo_url = !empty($options['logo_url']) ? $options['logo_url'] : '';
        $cookie_days = !empty($options['cookie_days']) ? (int)$options['cookie_days'] : 1;
        $content = !empty($options['content']) ? $options['content'] : '';

        if (empty($content)) return;

        ob_start();
        ?>
        <style>
            /* ==========================================================================
               LIQUID GLASS (GLASSMORPHISM) POPUP STYLES
               ========================================================================== */
            .sf-global-notice-overlay {
                position: fixed;
                top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(8, 37, 103, 0.25); /* Легкое затемнение фона в фирменном Navy */
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                z-index: 999999;
                display: flex;
                justify-content: center;
                align-items: center;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.5s ease, visibility 0.5s ease;
            }
            .sf-global-notice-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            .sf-global-notice-modal {
                /* Эффект жидкого стекла (White Glass) */
                background: rgba(255, 255, 255, 0.65);
                backdrop-filter: blur(30px) saturate(150%);
                -webkit-backdrop-filter: blur(30px) saturate(150%);
                border: 1px solid rgba(255, 255, 255, 0.8);
                box-shadow: 
                    0 25px 50px rgba(8, 37, 103, 0.15),
                    inset 0 0 0 1px rgba(255, 255, 255, 0.5),
                    inset 0 15px 30px rgba(255, 255, 255, 0.6);
                border-radius: 20px;
                padding: 30px 40px 25px 40px; /* Компактно по вертикали */
                width: 90%;
                max-width: 600px; /* Сделали чуть шире для пропорций */
                position: relative;
                transform: translateY(30px) scale(0.95);
                transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                text-align: center;
            }
            .sf-global-notice-overlay.active .sf-global-notice-modal {
                transform: translateY(0) scale(1);
            }

            /* Крестик закрытия */
            .sf-global-notice-close {
                position: absolute;
                top: 15px;
                right: 15px;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: rgba(255,255,255,0.5);
                border: 1px solid rgba(255,255,255,0.8);
                color: #64748b;
                font-size: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                line-height: 1;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            }
            .sf-global-notice-close:hover {
                background: #ffffff;
                color: #082567;
                transform: scale(1.1);
            }

            /* Логотип */
            .sf-global-notice-logo {
                margin-bottom: 20px;
            }
            .sf-global-notice-logo img {
                max-height: 45px;
                width: auto;
                filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05));
            }

            /* Контент */
            .sf-global-notice-content {
                font-family: 'Segoe UI', Roboto, sans-serif;
                color: #1e293b;
                font-size: 15px;
                line-height: 1.5;
            }
            /* Принудительная стилизация тегов из WYSIWYG */
            .sf-global-notice-content h2 {
                color: #082567;
                font-size: 22px;
                font-weight: 800;
                margin: 0 0 10px 0;
            }
            .sf-global-notice-content p {
                margin: 0 0 10px 0;
            }
            .sf-global-notice-content hr {
                border: 0;
                height: 1px;
                background: linear-gradient(to right, transparent, rgba(8, 37, 103, 0.2), transparent);
                margin: 15px 0;
            }

            /* Фирменная кнопка закрытия снизу */
            .sf-global-btn-wrap {
                margin-top: 20px;
            }
            .sf-notice-btn {
                position: relative;
                overflow: hidden;
                border-radius: 10px;
                border: none;
                box-shadow: 0 8px 16px rgba(0,0,0,0.15), inset 0 -3px 6px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.3);
                transition: all 0.25s ease;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 10px 30px;
                background-color: #082567;
                color: #E0B849;
                font-weight: 700;
                font-size: 14px;
                text-decoration: none;
                font-family: 'Segoe UI', Roboto, sans-serif;
            }
            .sf-notice-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 20px rgba(0,0,0,0.2), inset 0 -3px 6px rgba(0,0,0,0.2);
                background-color: #E0B849;
                color: #082567;
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

                <div class="sf-global-btn-wrap">
                    <button class="sf-notice-btn" id="sf-global-notice-btn">Verstanden / Got it</button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var overlay = document.getElementById('sf-global-notice');
                if (!overlay) return;
                
                var cookieDays = parseInt(overlay.getAttribute('data-days')) || 1;
                // Изменили ключ, чтобы сбросить кэш у тех, кто уже видел старую версию
                var cacheKey = 'sf_notice_dismissed_v2'; 
                var dismissUntil = localStorage.getItem(cacheKey);
                var now = new Date().getTime();

                // RU: Если кэша нет или он истек - показываем попап
                if (!dismissUntil || now > parseInt(dismissUntil)) {
                    setTimeout(function() {
                        overlay.classList.add('active');
                    }, 500); // Плавное появление через полсекунды после загрузки
                }

                function closeNotice() {
                    overlay.classList.remove('active');
                    // RU: Записываем в LocalStorage время, до которого не показывать
                    var expireTime = now + (cookieDays * 24 * 60 * 60 * 1000);
                    localStorage.setItem(cacheKey, expireTime);
                }

                // RU: Закрытие по крестику
                overlay.querySelector('.sf-global-notice-close').addEventListener('click', closeNotice);
                
                // RU: Закрытие по кнопке "Verstanden"
                document.getElementById('sf-global-notice-btn').addEventListener('click', closeNotice);
                
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
