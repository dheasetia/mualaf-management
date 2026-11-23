<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum ChecklistItemCode: string
{
    case ContactConfirmed = 'contact_confirmed';
    case AddressConfirmed = 'address_confirmed';
    case MosqueIdentified = 'mosque_identified';
    case LocalDaiIdentified = 'local_dai_identified';
    case ConsentObtained = 'consent_obtained';
    case ReferralSent = 'referral_sent';
    case AcknowledgmentReceived = 'acknowledgment_received';
    case FollowUpScheduled = 'follow_up_scheduled';

    public function label(): string
    {
        return __('international.checklist_items.'.$this->value);
    }
}
