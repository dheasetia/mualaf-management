<?php

declare(strict_types=1);

namespace App\Support\Antivirus;

interface FileScanner
{
    /**
     * Scans the bytes. Returns null when clean, else the signature found.
     *
     * @throws \RuntimeException when the scanner cannot be reached
     */
    public function scan(string $bytes): ?string;
}
