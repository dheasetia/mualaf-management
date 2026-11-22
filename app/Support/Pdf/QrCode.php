<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/**
 * Inline SVG QR codes (13-infrastructure-security §13.2).
 */
final class QrCode
{
    public static function svg(string $data): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'addQuietzone' => true,
        ]);

        return (new Generator($options))->render($data);
    }
}
