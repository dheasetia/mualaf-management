<?php

declare(strict_types=1);

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Jobs\RecordAuditLog;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Audit\Services\AuditRedactor;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->audit = app(AuditLogger::class);
});

it('records the authenticated user, the tenant in context and the subject', function (): void {
    $tenant = Tenant::factory()->active()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $entry = inTenant($tenant, fn (): AuditLog => $this->audit->record('test.recorded', $user));

    expect($entry->refresh())
        ->actor_id->toBe($user->id)
        ->actor_type->toBe(ActorType::User)
        ->tenant_id->toBe($tenant->id)
        ->subject_type->toBe('user')
        ->subject_id->toBe($user->id);
});

it('records system work without an actor and without a tenant', function (): void {
    expect($this->audit->record('test.recorded')->refresh())
        ->actor_id->toBeNull()
        ->actor_type->toBe(ActorType::System)
        ->tenant_id->toBeNull();
});

it('redacts hidden attributes and secrets at any depth', function (): void {
    $user = User::factory()->create();

    $entry = $this->audit->record('test.recorded', $user, newValues: [
        'name' => 'Visible',
        'phone_encrypted' => '0500000000',
        'nested' => ['Password' => 'hunter2-hunter2'],
    ])->refresh();

    // toEqual: MySQL JSON columns do not keep key order.
    expect($entry->new_values_redacted)->toEqual([
        'name' => 'Visible',
        'phone_encrypted' => AuditRedactor::MASK,
        'nested' => ['Password' => AuditRedactor::MASK],
    ]);
});

it('records old and new values of the last save only', function (): void {
    $user = User::factory()->create(['name' => 'Before']);
    $user->forceFill(['name' => 'After', 'password' => 'another-password-1'])->save();

    $entry = $this->audit->recordChanges('test.updated', $user)->refresh();

    expect($entry->old_values_redacted)->toMatchArray(['name' => 'Before', 'password' => AuditRedactor::MASK])
        ->and($entry->new_values_redacted)->toMatchArray(['name' => 'After', 'password' => AuditRedactor::MASK])
        ->and($entry->new_values_redacted)->not->toHaveKey('updated_at');
});

it('rolls back with the transaction it belongs to', function (): void {
    try {
        DB::transaction(function (): void {
            $this->audit->record('test.recorded');

            throw new RuntimeException('operation failed');
        });
    } catch (RuntimeException) {
    }

    expect(AuditLog::query()->where('action', 'test.recorded')->exists())->toBeFalse();
});

it('gives every entry of one request the same request id, address and user agent', function (): void {
    Route::get('/_audit-probe', function (AuditLogger $audit) {
        $audit->record('test.first');
        $audit->record('test.second');

        return 'ok';
    }); // AssignRequestId runs as global middleware.

    $response = $this->withHeaders(['User-Agent' => 'AuditProbe/1.0', 'X-Request-Id' => 'client-chosen'])
        ->get('/_audit-probe');

    $entries = AuditLog::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('request_id')->unique()->sole())->toBe($response->headers->get('X-Request-Id'))
        ->and($entries[0]->request_id)->not->toBe('client-chosen')
        ->and($entries[0]->ip_address)->toBe('127.0.0.1')
        ->and($entries[0]->user_agent)->toBe('AuditProbe/1.0');
});

it('queues read events on the audit queue with the context captured at dispatch', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    Context::addHidden(AssignRequestId::IP_KEY, '2001:db8::1');

    $this->audit->recordLater('document.downloaded', $user);

    expect(AuditLog::query()->count())->toBe(0);
    Queue::assertPushedOn(AuditLogger::QUEUE, RecordAuditLog::class);

    $job = Queue::pushed(RecordAuditLog::class)->sole();
    auth()->logout();
    Context::flush();
    $job->handle($this->audit);

    expect(AuditLog::query()->sole())
        ->action->toBe('document.downloaded')
        ->actor_id->toBe($user->id)
        ->ip_address->toBe('2001:db8::1');
});
