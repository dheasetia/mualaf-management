<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Companies\Models\Company;
use App\Domain\Converts\Enums\ContactRole;
use App\Domain\Converts\Enums\DisclosureScope;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by SaveConvertContactPerson. A third party is contacted only
 * when permission_to_contact is true; disclosure_scope says what they know
 * (04-identity-privacy §4.6).
 *
 * @property int $id
 * @property int $convert_id
 * @property int|null $company_id
 * @property int|null $residence_id
 * @property string $name
 * @property ContactRole $contact_role
 * @property string|null $phone_encrypted
 * @property bool $permission_to_contact
 * @property DisclosureScope $disclosure_scope
 * @property bool $is_primary
 */
final class ConvertContactPerson extends Model
{
    use ScopedThroughConvert;

    /** Laravel would pluralise the model to convert_contact_people. */
    protected $table = 'convert_contact_persons';

    protected $fillable = [];

    protected $hidden = ['phone_encrypted', 'alternate_phone_encrypted'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contact_role' => ContactRole::class,
            'phone_encrypted' => 'encrypted',
            'alternate_phone_encrypted' => 'encrypted',
            'permission_to_contact' => 'boolean',
            'disclosure_scope' => DisclosureScope::class,
            'is_primary' => 'boolean',
        ];
    }
}
