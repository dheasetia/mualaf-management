<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\RoleScope;
use Database\Factories\Identity\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property RoleScope $scope_type
 * @property bool $is_system
 * @property bool $requires_mfa
 */
#[UseFactory(RoleFactory::class)]
final class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name_ar', 'name_en', 'scope_type', 'is_system', 'requires_mfa', 'description'];

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    protected function casts(): array
    {
        return [
            'scope_type' => RoleScope::class,
            'is_system' => 'boolean',
            'requires_mfa' => 'boolean',
        ];
    }
}
