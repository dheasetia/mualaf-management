<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Converts\Scopes\ScopedThroughConvert;
use Illuminate\Database\Eloquent\Model;

/**
 * Written only by RecordShahadah. Sensitive like the shahadah record itself.
 *
 * @property int $id
 * @property int $shahadah_record_id
 * @property int $convert_id
 * @property string $name
 * @property string $phone_encrypted
 * @property string|null $identity_last4
 * @property string|null $relationship
 * @property int $created_by
 */
final class ShahadahWitness extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    protected $hidden = ['phone_encrypted'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'phone_encrypted' => 'encrypted',
        ];
    }
}
