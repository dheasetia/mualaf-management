<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum FollowUpState: string
{
    case ActiveGuidance = 'active_guidance';
    case Stable = 'stable';
    case NeedsFollowUp = 'needs_follow_up';
    case NeedsCommunityConnection = 'needs_community_connection';
    case TemporarilyUnreachable = 'temporarily_unreachable';
    case LostContact = 'lost_contact';
    case ReturnedHome = 'returned_home';
    case FollowedAbroad = 'followed_abroad';

    public function color(): string
    {
        return match ($this) {
            self::ActiveGuidance, self::Stable, self::FollowedAbroad => 'success',
            self::NeedsFollowUp, self::NeedsCommunityConnection => 'warning',
            self::TemporarilyUnreachable, self::LostContact => 'danger',
            self::ReturnedHome => 'info',
        };
    }

    public function label(): string
    {
        return __('followup.states.'.$this->value);
    }
}
