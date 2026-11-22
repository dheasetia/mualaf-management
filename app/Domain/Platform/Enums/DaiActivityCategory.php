<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

enum DaiActivityCategory: string
{
    case Dawah = 'dawah';
    case Education = 'education';
    case ScholarlyMedia = 'scholarly_media';
    case Social = 'social';

    public function label(): string
    {
        return __('platform.activity_categories.'.$this->value);
    }
}
