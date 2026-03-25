<?php

declare(strict_types=1);

namespace StayFlow\Onboarding;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.1.0
 * RU: Обработчик верификации email с защитой сессий.
 * EN: Email verification handler with session protection.
 */
final class VerificationHandler
{
    // ==========================================
    // INIT / ИНИЦИАЛИЗАЦИЯ
    // ==========================================
    public function register(): void
    {
        // RU: Вешаем на init, чтобы поймать запрос до того, как начнет грузиться страница
        // EN: Hook into init to catch the request before page load
        add_action('init', [$this, 'handleVerification']);
    }

    // ==========================================
    // ЛОГИКА ВЕРИФИКАЦИИ / VERIFICATION LOGIC
    // ==========================================
    public function handleVerification(): void
    {
        try {
            // RU: Проверяем, есть ли наши параметры в URL
            // EN: Check if our parameters are in URL
            if (!isset($_GET['sf_verify']) || !isset($_GET['sf_u'])) {
                return;
            }

            $token  = sanitize_text_field(wp_unslash($_GET['sf_verify']));
            $userId = (int)$_GET['sf_u'];

            if ($userId <= 0 || empty($token)) {
                $this->redirectWithError();
            }

            // RU: Защита сессии: если текущий юзер не совпадает с верифицируемым, выходим из системы
            // EN: Session protection: logout if current user doesn't match the one being verified
            if (is_user_logged_in() && get_current_user_id() !== $userId) {
                wp_logout();
            }

            // RU: Достаем токен из базы
            // EN: Retrieve token from DB
            $savedToken = get_user_meta($userId, '_sf_verify_token', true);

            // RU: Если токена нет (уже использован) или он не совпадает — выкидываем ошибку
            // EN: Error if token doesn't exist or doesn't match
            if (empty($savedToken) || $savedToken !== $token) {
                $this->redirectWithError();
            }

            // 1. УСПЕШНАЯ ВЕРИФИКАЦИЯ
            delete_user_meta($userId, '_sf_verify_token'); // Делаем ссылку одноразовой
            update_user_meta($userId, '_sf_account_status', 'verified'); // Меняем статус

            // 2. АВТОМАТИЧЕСКАЯ АВТОРИЗАЦИЯ
            $user = get_userdata($userId);
            if ($user) {
                wp_clear_auth_cookie(); // Очищаем старые куки на всякий случай
                wp_set_current_user($userId, $user->user_login);
                wp_set_auth_cookie($userId, true); // true = запомнить меня
                do_action('wp_login', $user->user_login, $user);
            }

            // 3. РЕДИРЕКТ В ДАШБОРД
            wp_safe_redirect(home_url('/owner-dashboard/'));
            exit;

        } catch (\Throwable $e) {
            error_log('StayFlow Verification Error: ' . $e->getMessage());
            $this->redirectWithError();
        }
    }

    // ==========================================
    // ОШИБКА / ERROR REDIRECT
    // ==========================================
    /**
     * RU: Если ссылка старая или битая, отправляем на страницу логина с ошибкой.
     * EN: Redirect to login with error if link is broken or expired.
     */
    private function redirectWithError(): void
    {
        wp_safe_redirect(home_url('/owner-login/?sf_error=invalid_token'));
        exit;
    }
}