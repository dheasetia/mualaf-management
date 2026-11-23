<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Enums\VerificationStatus;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Domain\Converts\Services\IdentityNumber;
use App\Domain\Platform\Models\Country;
use Carbon\CarbonImmutable;
use Database\Factories\Converts\ConvertIdentifierFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by IdentityRegistry. The number is encrypted, looked up by its
 * HMAC fingerprint and shown masked; the full number needs identity.reveal.
 *
 * @property int $id
 * @property int $convert_id
 * @property IdentityType $identity_type
 * @property string $identity_number_encrypted
 * @property string $identity_last4
 * @property string $identity_fingerprint
 * @property int $fingerprint_key_version
 * @property int $issuing_country_id
 * @property CarbonImmutable|null $expires_on
 * @property bool $is_primary
 * @property VerificationStatus $verification_status
 * @property CarbonImmutable|null $replaced_at
 * @property string|null $replaced_reason
 * @property int $created_by
 * @property-read Convert $convert
 */
#[UseFactory(ConvertIdentifierFactory::class)]
final class ConvertIdentifier extends Model
{
    /** @use HasFactory<ConvertIdentifierFactory> */
    use HasFactory;

    use ScopedThroughConvert;

    protected $fillable = [];

    /** Generated columns: never written by the application. */
    protected $guarded = ['active_fingerprint', 'primary_convert_key'];

    protected $hidden = ['identity_number_encrypted', 'identity_fingerprint', 'active_fingerprint'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'verification_status' => 'unverified',
    ];

    /** @return BelongsTo<Country, $this> */
    public function issuingCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'issuing_country_id');
    }

    public function masked(): string
    {
        return IdentityNumber::mask($this->identity_last4);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'identity_type' => IdentityType::class,
            'identity_number_encrypted' => 'encrypted',
            'expires_on' => 'immutable_date',
            'is_primary' => 'boolean',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'immutable_datetime',
            'replaced_at' => 'immutable_datetime',
        ];
    }
}
