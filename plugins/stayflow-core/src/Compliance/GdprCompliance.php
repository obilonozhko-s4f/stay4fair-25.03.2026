<?php

declare(strict_types=1);

namespace StayFlow\Compliance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.1.0
 *
 * RU: GDPR / DSGVO — Экспорт и удаление персональных данных.
 * - v1.1.0: Добавлены недостающие ключи (sf_company_name, sf_vat_id).
 * - v1.1.0: Добавлено физическое удаление PDF-файлов (Vouchers, Invoices) для 
 * бронирований, не подлежащих налоговому хранению по § 147 AO.
 */
final class GdprCompliance
{
    // -------------------------------------------------------------------------
    // Meta-ключи владельца (хранятся в usermeta)
    // -------------------------------------------------------------------------
    private const OWNER_META_KEYS = [
        'bsbt_iban',
        'bsbt_tax_number',
        'bsbt_account_holder',
        'bsbt_phone',
        'billing_address_1',
        'billing_address_2',
        'billing_postcode',
        'billing_city',
        'billing_country',
        'billing_phone',
        'billing_company',
        'kontonummer',
        'kontoinhaber',
        'steuernummer',
        'sf_company_name', // Добавлено: Название компании
        'sf_vat_id',       // Добавлено: ИНН/VAT
    ];

    // -------------------------------------------------------------------------
    // Meta-ключи гостя (хранятся в postmeta бронирования MPHB)
    // -------------------------------------------------------------------------
    private const GUEST_META_KEYS = [
        'mphb_first_name',
        'mphb_last_name',
        'mphb_email',
        'mphb_phone',
        '_mphb_phone',
        'mphb_address',
        'mphb_city',
        'mphb_zip',
        'mphb_country',
        'mphb_company',
        'mphb_note',
        'mphb_address1',   // Добавлено (использовалось в ваучерах)
    ];

    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerErasers']);
    }

    public function registerExporters(array $exporters): array
    {
        $exporters['stayflow-owner-data'] = [
            'exporter_friendly_name' => 'Stay4Fair – Vermieter-Daten',
            'callback'               => [$this, 'exportOwnerData'],
        ];

        $exporters['stayflow-guest-data'] = [
            'exporter_friendly_name' => 'Stay4Fair – Gast-Buchungsdaten',
            'callback'               => [$this, 'exportGuestData'],
        ];

        return $exporters;
    }

    public function registerErasers(array $erasers): array
    {
        $erasers['stayflow-owner-data'] = [
            'eraser_friendly_name' => 'Stay4Fair – Vermieter-Daten',
            'callback'             => [$this, 'eraseOwnerData'],
        ];

        $erasers['stayflow-guest-data'] = [
            'eraser_friendly_name' => 'Stay4Fair – Gast-Buchungsdaten',
            'callback'             => [$this, 'eraseGuestData'],
        ];

        return $erasers;
    }

    // -------------------------------------------------------------------------
    // ЭКСПОРТ (Exporters)
    // -------------------------------------------------------------------------

    public function exportOwnerData(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return ['data' => [], 'done' => true];
        }

        $dataItems = [];
        foreach (self::OWNER_META_KEYS as $key) {
            $value = get_user_meta($user->ID, $key, true);
            if ($value !== '' && $value !== false && $value !== null) {
                $dataItems[] = [
                    'name'  => $key,
                    'value' => (string) $value,
                ];
            }
        }

        if (empty($dataItems)) {
            return ['data' => [], 'done' => true];
        }

        return [
            'data' => [
                [
                    'group_id'    => 'stayflow-owner',
                    'group_label' => 'Stay4Fair Vermieter-Profil',
                    'item_id'     => 'owner-' . $user->ID,
                    'data'        => $dataItems,
                ],
            ],
            'done' => true,
        ];
    }

    public function exportGuestData(string $email, int $page = 1): array
    {
        $bookings = $this->findBookingsByEmail($email);
        if (empty($bookings)) {
            return ['data' => [], 'done' => true];
        }

        $exportData = [];
        foreach ($bookings as $bookingId) {
            $dataItems = [];
            foreach (self::GUEST_META_KEYS as $key) {
                $value = get_post_meta($bookingId, $key, true);
                if ($value !== '' && $value !== false && $value !== null) {
                    $dataItems[] = [
                        'name'  => $key,
                        'value' => (string) $value,
                    ];
                }
            }

            if (!empty($dataItems)) {
                $exportData[] = [
                    'group_id'    => 'stayflow-booking',
                    'group_label' => 'Stay4Fair Buchungsdaten',
                    'item_id'     => 'booking-' . $bookingId,
                    'data'        => $dataItems,
                ];
            }
        }

        return ['data' => $exportData, 'done' => true];
    }

    // -------------------------------------------------------------------------
    // УДАЛЕНИЕ (Erasers)
    // -------------------------------------------------------------------------

    public function eraseOwnerData(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        $removed  = false;
        $retained = false;
        $messages = [];

        foreach (self::OWNER_META_KEYS as $key) {
            $value = get_user_meta($user->ID, $key, true);
            if ($value === '' || $value === false || $value === null) {
                continue;
            }

            if (in_array($key, ['bsbt_iban', 'kontonummer', 'bsbt_tax_number', 'steuernummer', 'sf_vat_id'], true)) {
                update_user_meta($user->ID, $key, '[GDPR-gelöscht]');
                $retained = true;
                $messages[] = sprintf('Feld "%s" wurde anonymisiert (steuerliche Aufbewahrungspflicht § 147 AO).', esc_html($key));
            } else {
                delete_user_meta($user->ID, $key);
                $removed = true;
            }
        }

        return ['items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => true];
    }

    public function eraseGuestData(string $email, int $page = 1): array
    {
        $bookings = $this->findBookingsByEmail($email);
        if (empty($bookings)) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        $removed  = false;
        $retained = false;
        $messages = [];

        foreach ($bookings as $bookingId) {
            $status = get_post_status($bookingId);
            // Если бронирование оплачено/подтверждено, мы обязаны хранить его для налоговой (10 лет)
            $isFinalized = in_array($status, ['confirmed', 'cancelled', 'trash'], true);

            foreach (self::GUEST_META_KEYS as $key) {
                $value = get_post_meta($bookingId, $key, true);
                if ($value === '' || $value === false || $value === null) continue;

                if ($isFinalized) {
                    update_post_meta($bookingId, $key, '[GDPR-anonymisiert]');
                    $retained = true;
                } else {
                    delete_post_meta($bookingId, $key);
                    $removed = true;
                }
            }

            // Физическое удаление PDF файлов (только если бронирование НЕ финализировано)
            if (!$isFinalized) {
                if ($this->deletePhysicalPdfs($bookingId)) {
                    $messages[] = sprintf('Buchung #%d: PDF-Dokumente wurden gelöscht.', $bookingId);
                }
            }

            if ($isFinalized) {
                $messages[] = sprintf('Buchung #%d: Daten anonymisiert (Aufbewahrungspflicht § 147 AO).', $bookingId);
            }
        }

        return ['items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => true];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findBookingsByEmail(string $email): array
    {
        $safeEmail = sanitize_email($email);
        if (!is_email($safeEmail)) return [];

        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('mphb_email', '_mphb_email') AND meta_value = %s",
            $safeEmail
        ));

        return array_map('intval', $ids ?: []);
    }

    /**
     * Удаляет физические PDF файлы (Ваучеры и отчеты владельца) для указанного бронирования.
     */
    private function deletePhysicalPdfs(int $bookingId): bool
    {
        $uploadDir = wp_upload_dir();
        if (empty($uploadDir['basedir'])) return false;

        $deleted = false;
        
        // 1. Ищем ваучеры (Voucher-1234-*.pdf)
        $voucherDir = trailingslashit($uploadDir['basedir']) . 'bs-vouchers/';
        $vouchers = glob($voucherDir . 'Voucher-' . $bookingId . '*.pdf');
        if (is_array($vouchers)) {
            foreach ($vouchers as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $deleted = true;
                }
            }
        }

        // 2. Ищем Owner PDF (Owner_PDF_1234.pdf)
        $ownerPdfDir = trailingslashit($uploadDir['basedir']) . 'bsbt-owner-pdf/';
        $ownerPdfs = glob($ownerPdfDir . 'Owner_PDF_' . $bookingId . '.pdf');
        if (is_array($ownerPdfs)) {
            foreach ($ownerPdfs as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $deleted = true;
                }
            }
        }

        return $deleted;
    }
}
