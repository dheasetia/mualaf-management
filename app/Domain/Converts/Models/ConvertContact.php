<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Converts\Enums\ContactType;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Written only by AddConvertContact / RemoveConvertContact. Removed contacts
 * stay with active = false.
 *
 * @property int $id
 * @property int $convert_id
 * @property ContactType $contact_type
 * @property string $value_encrypted
 * @property string $value_fingerprint
 * @property bool $is_primary
 * @property CarbonImmutable|null $verified_at
 * @property bool $active
 * @property int $created_by
 */
final class ConvertContact extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    protected $hidden = ['value_encrypted', 'value_fingerprint'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'active' => true,
        'is_primary' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contact_type' => ContactType::class,
            'value_encrypted' => 'encrypted',
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'active' => 'boolean',
        ];
    }
}
