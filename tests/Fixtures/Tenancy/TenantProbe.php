<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tenancy;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 */
final class TenantProbe extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'tenant_id'];
}
