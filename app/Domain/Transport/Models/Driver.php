<?php

declare(strict_types=1);

namespace App\Domain\Transport\Models;

use App\Domain\Transport\Enums\DriverStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Transport\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $name
 * @property string $phone_encrypted
 * @property string|null $license_number_encrypted
 * @property CarbonImmutable|null $license_expires_on
 * @property DriverStatus $status
 */
#[UseFactory(DriverFactory::class)]
final class Driver extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    protected $fillable = [];

    protected $hidden = ['phone_encrypted', 'license_number_encrypted'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'phone_encrypted' => 'encrypted',
            'license_number_encrypted' => 'encrypted',
            'license_expires_on' => 'immutable_date',
            'status' => DriverStatus::class,
        ];
    }
}
