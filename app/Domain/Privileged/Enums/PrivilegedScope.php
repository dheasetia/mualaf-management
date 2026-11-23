<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Enums;

/** What a grant may show of one convert. */
enum PrivilegedScope: string
{
    case Profile = 'profile';
    case Contacts = 'contacts';
    case Identity = 'identity';
    case Shahadah = 'shahadah';
    case FollowUps = 'follow_ups';

    public function label(): string
    {
        return __('privileged.scopes.'.$this->value);
    }
}
