<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\International\Enums\OrganizationStatus;
use App\Domain\International\Enums\OrganizationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * National directory of Islamic organizations abroad (10 §10.7),
 * kept by Central (international.organizations.manage).
 *
 * @property int $id
 * @property string $uuid
 * @property int $country_id
 * @property string $city
 * @property string $organization_name
 * @property OrganizationType $organization_type
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property OrganizationStatus $verification_status
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 */
final class ExternalIslamicOrganization extends Model
{
    use HasUuids;

    protected $fillable = [];

    /** Directory fields are filled from the Central form; verification only through its actions. */
    protected $guarded = ['id', 'uuid', 'verification_status', 'verified_by', 'verified_at'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'organization_type' => OrganizationType::class,
            'verification_status' => OrganizationStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
