<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Data\ShahadahWitnessData;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahWitness;
use App\Models\User;
use App\Support\Identity\Digits;
use App\Support\Identity\PhoneNumber;

/**
 * The witness rules of a shahadah (open question #2), shared by registration
 * and correction: at least SHAHADAH_MIN_WITNESSES, at most the maximum,
 * each with a name and a valid phone (stored as +digits), no phone twice,
 * and an optional ID suffix of exactly four digits.
 */
final class ShahadahWitnesses
{
    /**
     * @param  list<ShahadahWitnessData>  $witnesses
     * @return list<ShahadahWitnessData>
     */
    public function normalize(array $witnesses): array
    {
        $minimum = (int) config('registration.shahadah_min_witnesses');
        $maximum = (int) config('registration.shahadah_max_witnesses');

        if (count($witnesses) < $minimum) {
            throw RegistrationRuleViolation::witnessesRequired($minimum);
        }

        if (count($witnesses) > $maximum) {
            throw RegistrationRuleViolation::tooManyWitnesses($maximum);
        }

        $normalized = [];

        foreach ($witnesses as $witness) {
            $name = trim($witness->name);
            $phone = PhoneNumber::normalize($witness->phone);
            $last4 = $witness->identityLast4 === null ? null : Digits::toAscii(trim($witness->identityLast4));

            if ($name === '' || $phone === null || ($last4 !== null && preg_match('/^\d{4}$/', $last4) !== 1)) {
                throw RegistrationRuleViolation::invalidWitness();
            }

            if (array_key_exists($phone, $normalized)) {
                throw RegistrationRuleViolation::duplicateWitness();
            }

            $relationship = $witness->relationship === null ? null : trim($witness->relationship);
            $normalized[$phone] = new ShahadahWitnessData($name, '+'.$phone, $last4, $relationship === '' ? null : $relationship);
        }

        return array_values($normalized);
    }

    /** @param  list<ShahadahWitnessData>  $witnesses  normalised */
    public function store(ShahadahRecord $shahadah, array $witnesses, User $actor): void
    {
        foreach ($witnesses as $witness) {
            $row = new ShahadahWitness;
            $row->shahadah_record_id = $shahadah->id;
            $row->convert_id = $shahadah->convert_id;
            $row->name = $witness->name;
            $row->phone_encrypted = $witness->phone;
            $row->identity_last4 = $witness->identityLast4;
            $row->relationship = $witness->relationship;
            $row->created_by = $actor->id;
            $row->save();
        }
    }
}
