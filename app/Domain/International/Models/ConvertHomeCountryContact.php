<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\Converts\Enums\DisclosureScope;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Domain\International\Enums\HomeRelationship;
use Illuminate\Database\Eloquent\Model;

/**
 * A contact in the convert's home country (10 §10.6).
 *
 * @property int $id
 * @property int $convert_id
 * @property int $country_id
 * @property string $contact_name
 * @property HomeRelationship $relationship
 * @property string|null $phone_encrypted
 * @property string|null $whatsapp_encrypted
 * @property string|null $email_encrypted
 * @property string|null $city
 * @property bool $is_primary
 * @property bool $permission_to_contact
 * @property DisclosureScope $disclosure_scope
 * @property int $created_by
 */
final class ConvertHomeCountryContact extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    protected $hidden = ['phone_encrypted', 'whatsapp_encrypted', 'email_encrypted'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relationship' => HomeRelationship::class,
            'phone_encrypted' => 'encrypted',
            'whatsapp_encrypted' => 'encrypted',
            'email_encrypted' => 'encrypted',
            'is_muslim' => 'boolean',
            'is_primary' => 'boolean',
            'permission_to_contact' => 'boolean',
            'disclosure_scope' => DisclosureScope::class,
        ];
    }
}
