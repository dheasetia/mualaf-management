<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class ProfilePhotoRejected extends RuleViolation
{
    public static function tooLarge(): self
    {
        return new self(__('identity.profile.photo_too_large'), 'photo');
    }

    public static function notAnImage(): self
    {
        return new self(__('identity.profile.photo_not_image'), 'photo');
    }
}
