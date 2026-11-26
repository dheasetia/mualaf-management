<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Services\ProfilePhotos;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A profile photo, for signed-in users only. Addressed by the user's UUID, so
 * photos cannot be listed by counting ids.
 */
final class ShowProfilePhotoController extends Controller
{
    public function __invoke(string $uuid): Response
    {
        abort_unless(auth()->check(), 404);

        $path = User::query()->where('uuid', $uuid)->value('avatar_path');

        abort_unless(is_string($path) && Storage::disk(ProfilePhotos::DISK)->exists($path), 404);

        return Storage::disk(ProfilePhotos::DISK)->response($path, headers: [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
