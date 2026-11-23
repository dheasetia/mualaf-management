<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Switches the application to $key, with $previous as APP_PREVIOUS_KEYS. */
function useEncryptionKeys(string $key, array $previous = []): void
{
    config(['app.key' => $key, 'app.previous_keys' => $previous]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
}

it('rewrites encrypted columns with the new key so the old key can be dropped', function (): void {
    $old = (string) config('app.key');
    $new = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
    $tenant = Tenant::factory()->active()->create();
    $prospect = inTenant($tenant, fn () => Prospect::factory()->create(['phone_encrypted' => '966501234567', 'email_encrypted' => 'person@example.org']));
    $before = DB::table('prospects')->where('id', $prospect->id)->value('phone_encrypted');

    useEncryptionKeys($new, [$old]);
    $this->artisan('security:reencrypt', ['--dry-run' => true])->assertSuccessful();
    expect(DB::table('prospects')->where('id', $prospect->id)->value('phone_encrypted'))->toBe($before);

    $this->artisan('security:reencrypt')->assertSuccessful();
    useEncryptionKeys($new);

    $fresh = inTenant($tenant, fn () => Prospect::query()->findOrFail($prospect->id));
    expect($fresh->phone_encrypted)->toBe('966501234567')
        ->and($fresh->email_encrypted)->toBe('person@example.org')
        ->and(AuditLog::query()->where('action', 'security.reencrypted')->sole()->new_values_redacted['failures'])->toBe(0);

    useEncryptionKeys($old);
});

it('fails and keeps going when a value cannot be decrypted with any known key', function (): void {
    $old = (string) config('app.key');
    $tenant = Tenant::factory()->active()->create();
    inTenant($tenant, fn () => Prospect::factory()->create(['phone_encrypted' => '966501234567']));

    useEncryptionKeys('base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC')));
    $this->artisan('security:reencrypt')->assertFailed();

    useEncryptionKeys($old);
});
