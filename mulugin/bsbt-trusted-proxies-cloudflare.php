<?php
/**
 * Plugin Name: BSBT Trusted Proxies - Cloudflare
 * Description: Добавляет официальные Cloudflare IP ranges в trusted proxies для StayFlow SecurityGuard.
 * Version: 1.0.0
 * Author: BS Business Travelling
 *
 * RU:
 * MU-плагин для безопасного определения реального IP за Cloudflare.
 * Используется фильтром stayflow/security/trusted_proxies.
 *
 * ВАЖНО:
 * - Этот файл имеет смысл только если сайт реально стоит за Cloudflare proxy.
 * - Список диапазонов нужно периодически сверять с официальной страницей Cloudflare:
 *   https://developers.cloudflare.com/fundamentals/concepts/cloudflare-ip-addresses/
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('stayflow/security/trusted_proxies', static function (array $proxies): array {
    /**
     * RU:
     * Официальные Cloudflare IP ranges.
     * Держим списком внутри MU-плагина, чтобы логика SecurityGuard не зависела от темы.
     *
     * При обновлении обязательно сверять с:
     * https://www.cloudflare.com/ips/
     * и/или
     * https://developers.cloudflare.com/fundamentals/concepts/cloudflare-ip-addresses/
     */
    $cloudflareIps = [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',

        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    $merged = array_merge($proxies, $cloudflareIps);

    // Нормализация: только строки, trim, unique.
    $normalized = [];
    foreach ($merged as $entry) {
        if (!is_string($entry)) {
            continue;
        }

        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        $normalized[] = $entry;
    }

    return array_values(array_unique($normalized));
}, 10, 1);
