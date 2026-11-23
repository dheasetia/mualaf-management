<?php

declare(strict_types=1);

namespace App\Support\Pdf;

/**
 * Turns a full HTML document into PDF bytes. Implemented with Gotenberg
 * (Chromium), which shapes Arabic correctly (13-infrastructure-security §13.2).
 */
interface PdfRenderer
{
    /**
     * @param  'portrait'|'landscape'  $orientation
     */
    public function render(string $html, string $orientation = 'portrait'): string;
}
