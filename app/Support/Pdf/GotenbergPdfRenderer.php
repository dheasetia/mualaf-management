<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gotenberg 8 Chromium route: POST /forms/chromium/convert/html with the
 * document as index.html. A4, no margins (templates set their own).
 */
final class GotenbergPdfRenderer implements PdfRenderer
{
    private const float A4_SHORT_INCHES = 8.27;

    private const float A4_LONG_INCHES = 11.69;

    public function render(string $html, string $orientation = 'portrait'): string
    {
        $landscape = $orientation === 'landscape';

        $response = Http::timeout((int) config('services.gotenberg.timeout'))
            ->attach('files', $html, 'index.html', ['Content-Type' => 'text/html'])
            ->post(rtrim((string) config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                'paperWidth' => (string) ($landscape ? self::A4_LONG_INCHES : self::A4_SHORT_INCHES),
                'paperHeight' => (string) ($landscape ? self::A4_SHORT_INCHES : self::A4_LONG_INCHES),
                'marginTop' => '0',
                'marginBottom' => '0',
                'marginLeft' => '0',
                'marginRight' => '0',
                'printBackground' => 'true',
            ]);

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF')) {
            throw new RuntimeException('Gotenberg did not return a PDF (HTTP '.$response->status().').');
        }

        return $response->body();
    }
}
