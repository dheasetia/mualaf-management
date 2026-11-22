<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\DocumentRuleViolation;

/**
 * Upload checks of 13 §13.1: the type is read from the content (not the
 * name or the browser's claim), the size is limited, and images are
 * re-encoded so EXIF metadata such as GPS location is dropped.
 */
final class FileInspector
{
    /**
     * @return array{bytes: string, mime: string, extension: string}
     */
    public function inspect(string $path): array
    {
        $size = (int) filesize($path);

        if ($size > (int) config('documents.max_bytes')) {
            throw DocumentRuleViolation::fileTooLarge();
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        /** @var array<string, string> $allowed */
        $allowed = config('documents.allowed_mime_types');

        if (! isset($allowed[$mime])) {
            throw DocumentRuleViolation::fileType();
        }

        $bytes = (string) file_get_contents($path);

        if ($mime !== 'application/pdf') {
            $bytes = $this->withoutMetadata($bytes, $mime);
        }

        return ['bytes' => $bytes, 'mime' => $mime, 'extension' => $allowed[$mime]];
    }

    private function withoutMetadata(string $bytes, string $mime): string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw DocumentRuleViolation::fileType();
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $image = $this->upright($image, $bytes);
        }

        if ($mime !== 'image/jpeg') {
            imagesavealpha($image, true);
        }

        ob_start();
        match ($mime) {
            'image/png' => imagepng($image),
            'image/webp' => imagewebp($image, null, 90),
            default => imagejpeg($image, null, 90),
        };

        return (string) ob_get_clean();
    }

    /** Applies the EXIF orientation before the metadata is dropped. */
    private function upright(\GdImage $image, string $bytes): \GdImage
    {
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
