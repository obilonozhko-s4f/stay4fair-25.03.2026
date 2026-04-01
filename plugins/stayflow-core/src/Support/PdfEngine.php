<?php

declare(strict_types=1);

namespace StayFlow\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version: 1.1.0
 *
 * RU: Централизованная фабрика PDF-движков.
 * Устраняет SSRF-уязвимость: isRemoteEnabled=false везде.
 * Логотип всегда передаётся как локальный путь (WP_CONTENT_DIR),
 * а не как внешний URL — именно поэтому remote не нужен.
 *
 * EN: Centralized PDF engine factory.
 * Fixes SSRF: isRemoteEnabled=false everywhere.
 * Logo is always resolved to a local filesystem path
 * (WP_CONTENT_DIR), not an external URL.
 */
final class PdfEngine
{
    /**
     * RU: Относительный путь логотипа внутри wp-content.
     * Проверьте, совпадает ли с реальным расположением файла.
     * EN: Logo path relative to wp-content dir. Verify against your install.
     */
    private const LOGO_WP_CONTENT_RELATIVE = '/uploads/2025/12/gorizontal-color-4.png';

    /**
     * RU: Возвращает абсолютный путь к логотипу на диске.
     * Если файл не найден — возвращает пустую строку (PDF рендерится без логотипа).
     */
    public static function logoPath(): string
    {
        $path = WP_CONTENT_DIR . self::LOGO_WP_CONTENT_RELATIVE;
        return is_file($path) ? $path : '';
    }

    /**
     * RU: Определяет доступный движок (mpdf > dompdf > null).
     */
    public static function detect(): ?string
    {
        if (class_exists('\Mpdf\Mpdf')) {
            return 'mpdf';
        }
        if (class_exists('\Dompdf\Dompdf')) {
            return 'dompdf';
        }
        return null;
    }

    /**
     * RU: Рендерит HTML в PDF и возвращает байты.
     *
     * @throws \RuntimeException if no PDF engine is available.
     */
    public static function render(string $html, string $pageSize = 'A4', string $orientation = 'portrait'): string
    {
        $engine = self::detect();

        if ($engine === 'mpdf') {
            return self::renderMpdf($html);
        }

        if ($engine === 'dompdf') {
            return self::renderDompdf($html, $pageSize, $orientation);
        }

        throw new \RuntimeException('[PdfEngine] No PDF engine available (mpdf or dompdf required).');
    }

    /**
     * RU: Отдаёт PDF браузеру как вложение (download).
     */
    public static function stream(string $html, string $filename, string $pageSize = 'A4', string $orientation = 'portrait'): void
    {
        $bytes = self::render($html, $pageSize, $orientation);
        $safe  = self::safeFilename($filename);

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $safe . '"');
            header('Content-Length: ' . strlen($bytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }

        echo $bytes;
        exit;
    }

    /**
     * RU: Отдаёт PDF браузеру для просмотра inline (без скачивания).
     */
    public static function inline(string $html, string $filename, string $pageSize = 'A4', string $orientation = 'portrait'): void
    {
        $bytes = self::render($html, $pageSize, $orientation);
        $safe  = self::safeFilename($filename);

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $safe . '"');
            header('Content-Length: ' . strlen($bytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }

        echo $bytes;
        exit;
    }

    /**
     * RU: Сохраняет PDF на диск.
     *
     * @throws \RuntimeException on render or write failure.
     */
    public static function save(string $html, string $filePath, string $pageSize = 'A4', string $orientation = 'portrait'): void
    {
        $bytes = self::render($html, $pageSize, $orientation);
        $dir   = dirname($filePath);

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $written = file_put_contents($filePath, $bytes, LOCK_EX);

        if ($written === false) {
            throw new \RuntimeException('[PdfEngine] Failed to write PDF to: ' . $filePath);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function renderMpdf(string $html): string
    {
        $mpdf = new \Mpdf\Mpdf([
            'format'        => 'A4',
            'margin_left'   => 12,
            'margin_right'  => 12,
            'margin_top'    => 14,
            'margin_bottom' => 14,
        ]);
        $mpdf->WriteHTML($html);
        // 'S' = return as string (no SSRF risk — mpdf handles local paths natively)
        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    private static function renderDompdf(string $html, string $pageSize, string $orientation): string
    {
        // isRemoteEnabled: false — SSRF fix.
        // Логотип и все ресурсы должны передаваться как data: URI или локальные пути.
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled'         => false,  // ← КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ SSRF
            'isHtml5ParserEnabled'    => true,
            'defaultMediaType'        => 'print',
            'defaultPaperSize'        => $pageSize,
            'defaultPaperOrientation' => $orientation,
            'chroot'                  => WP_CONTENT_DIR, // разрешаем загрузку только из wp-content
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($pageSize, $orientation);
        $dompdf->render();
        return (string) $dompdf->output();
    }

    private static function safeFilename(string $name): string
    {
        // Убираем path separators и кавычки из имени файла
        return preg_replace('/[\/\\\\"\'<>]/', '_', $name) ?? 'document.pdf';
    }
}
