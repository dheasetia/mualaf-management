<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Services;

/**
 * Fills a certificate template with its snapshot. Templates are data, not
 * code: only {{ field }} placeholders are replaced, every value is HTML
 * escaped, and unknown fields become empty. The QR code is the one raw
 * insertion ({{ qr_code }}), generated here. (Replaces the "Blade sandbox"
 * of 13-infrastructure-security §13.2: nothing in a template is executed.)
 */
final class TemplateRenderer
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function render(string $template, array $snapshot, ?string $qrSvg): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $match) use ($snapshot, $qrSvg): string {
            if ($match[1] === 'qr_code') {
                return $qrSvg ?? '';
            }

            $value = $snapshot[$match[1]] ?? '';

            return is_scalar($value) ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        }, $template);
    }
}
