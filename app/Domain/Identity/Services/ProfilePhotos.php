<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\ProfilePhotoRejected;
use App\Models\User;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A user's own profile photo. The upload is decoded and drawn again as a square
 * JPEG, so no metadata (EXIF, GPS) or hidden content survives; it is kept on
 * the private disk and served only to signed-in users (ShowProfilePhoto).
 */
final readonly class ProfilePhotos
{
    public const string DISK = 'local';

    public const int MAX_BYTES = 2 * 1024 * 1024;

    public const int SIZE = 256;

    /** @var list<string> */
    public const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private AuditLogger $audit) {}

    public function replace(User $user, string $uploadedPath): void
    {
        if ((int) @filesize($uploadedPath) > self::MAX_BYTES) {
            throw ProfilePhotoRejected::tooLarge();
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($uploadedPath);
        $bytes = (string) file_get_contents($uploadedPath);
        $image = in_array($mime, self::MIME_TYPES, true) ? @imagecreatefromstring($bytes) : false;

        if ($image === false) {
            throw ProfilePhotoRejected::notAnImage();
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $image = $this->upright($image, $bytes);
        }

        $path = sprintf('profile-photos/%s-%s.jpg', $user->uuid, Str::lower(Str::random(12)));
        Storage::disk(self::DISK)->put($path, $this->squareJpeg($image));

        $old = $user->avatar_path;
        $user->avatar_path = $path;
        $user->save();
        $this->deleteFile($old);

        $this->audit->record('account.photo_changed', $user, newValues: ['photo' => true], actor: $user);
    }

    public function remove(User $user): void
    {
        if ($user->avatar_path === null) {
            return;
        }

        $this->deleteFile($user->avatar_path);
        $user->avatar_path = null;
        $user->save();

        $this->audit->record('account.photo_changed', $user, newValues: ['photo' => false], actor: $user);
    }

    private function squareJpeg(GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, (int) imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);

        return (string) ob_get_clean();
    }

    /** Applies the EXIF orientation before the metadata is dropped. */
    private function upright(GdImage $image, string $bytes): GdImage
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

    private function deleteFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
