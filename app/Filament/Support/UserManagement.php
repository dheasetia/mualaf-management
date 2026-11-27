<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Localization\LocalizedName;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Pieces shared by the user and role screens of both panels.
 */
final class UserManagement
{
    public static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    public static function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        return $tenant;
    }

    /**
     * Runs a domain action; a broken rule is shown next to its form field.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $action
     * @return TResult
     */
    public static function run(Closure $action): mixed
    {
        try {
            return $action();
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }

    /** @return array<int, string> */
    public static function roleOptions(RoleScope $scope): array
    {
        return Role::query()
            ->where('scope_type', $scope)
            ->orderByDesc('is_system')
            ->orderBy(LocalizedName::column())
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->id => self::roleLabel($role)])
            ->all();
    }

    public static function roleLabel(Role $role): string
    {
        $name = (string) LocalizedName::of($role);

        return $role->is_system ? $name : $name.' ('.__('roles.types.custom').')';
    }

    /** @return list<int> */
    public static function currentRoleIds(User $user, ?Tenant $tenant): array
    {
        return UserRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant?->id)
            ->whereNull('revoked_at')
            ->pluck('role_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return list<string> */
    public static function roleNames(User $user, ?int $tenantId): array
    {
        return $user->roleAssignments
            ->filter(fn (UserRoleAssignment $assignment): bool => $assignment->revoked_at === null && $assignment->tenant_id === $tenantId)
            ->map(fn (UserRoleAssignment $assignment): string => self::roleLabel($assignment->role))
            ->values()
            ->all();
    }

    /** @return list<TextInput> */
    public static function passwordFields(bool $required): array
    {
        return [
            TextInput::make('password')
                ->label(__($required ? 'users.fields.password' : 'users.fields.new_password'))
                ->helperText(__($required ? 'users.fields.password_help' : 'users.fields.new_password_help'))
                ->password()
                ->revealable()
                ->required($required)
                ->rule(Password::defaults())
                ->same('password_confirmation'),
            TextInput::make('password_confirmation')
                ->label(__('users.fields.password_confirmation'))
                ->password()
                ->revealable()
                ->required(fn (Get $get): bool => $required || filled($get('password')))
                ->dehydrated(false),
        ];
    }
}
