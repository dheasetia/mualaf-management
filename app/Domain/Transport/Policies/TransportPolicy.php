<?php

declare(strict_types=1);

namespace App\Domain\Transport\Policies;

use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Transport screens: read by any transport permission; the fleet is kept by
 * transport.fleet.manage and trips by transport.dispatch. Workflow steps
 * are checked by the Transport actions. Nothing is deleted.
 */
final class TransportPolicy
{
    private const array ANY = ['transport.view', 'transport.request', 'transport.approve', 'transport.dispatch', 'transport.fleet.manage'];

    public function viewAny(User $user): bool
    {
        return collect(self::ANY)->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transport.fleet.manage') || $user->hasPermission('transport.dispatch');
    }

    public function update(User $user, Model $model): bool
    {
        return match (true) {
            $model instanceof Vehicle, $model instanceof Driver => $user->hasPermission('transport.fleet.manage'),
            $model instanceof TransportTrip => $user->hasPermission('transport.dispatch'),
            default => false,
        };
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
