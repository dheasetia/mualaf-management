<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Notifications\NewDeviceLoginNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 13 §13.6 (account takeover): remembers the browsers an account signs in
 * from as a keyed hash of the user agent. A sign-in from a browser not seen
 * within KNOWN_DEVICE_DAYS, on an account that already has a known one,
 * emails the owner and is audited. The very first sign-in is not reported.
 */
final readonly class KnownDevices
{
    public function __construct(private AuditLogger $audit) {}

    public function remember(User $user, ?string $userAgent, ?string $ipAddress): bool
    {
        $fingerprint = hash_hmac('sha256', trim((string) $userAgent), (string) config('app.key'));
        $now = CarbonImmutable::now();
        $known = DB::table('user_login_devices')->where('user_id', $user->id)->where('fingerprint', $fingerprint)->first();
        $hasOthers = DB::table('user_login_devices')->where('user_id', $user->id)->where('fingerprint', '!=', $fingerprint)->exists();
        $isNew = $known === null || CarbonImmutable::parse((string) $known->last_seen_at)->lessThan($now->subDays((int) config('security.known_device_days')));

        DB::table('user_login_devices')->upsert(
            [['user_id' => $user->id, 'fingerprint' => $fingerprint, 'first_seen_at' => $now, 'last_seen_at' => $now]],
            ['user_id', 'fingerprint'],
            ['last_seen_at'],
        );

        if (! $isNew || ($known === null && ! $hasOthers)) {
            return false;
        }

        $user->notify(new NewDeviceLoginNotification($ipAddress, mb_substr((string) $userAgent, 0, 200), $now));
        $this->audit->record('auth.new_device', $user, newValues: ['ip' => $ipAddress], actor: $user);

        return true;
    }
}
