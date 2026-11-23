<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Platform\Models\Country;
use App\Domain\Reporting\Actions\Exports;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ExportType;
use App\Domain\Reporting\Models\ExportJob;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/exports')]);
    Storage::forgetDisk('local');
    File::deleteDirectory(storage_path('framework/testing/disks/exports'));
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create();
    $this->admin = grantRole(memberOf($this->tenant), 'center_admin', $this->tenant);
    RateLimiter::clear('exports:'.$this->admin->id);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->unzip = function (ExportJob $export, string $password): string|false {
        $file = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($file, Storage::disk('local')->get((string) $export->storage_path));
        $zip = new ZipArchive;
        $zip->open($file);
        $zip->setPassword($password);

        return $zip->getFromIndex(0);
    };
});

it('exports the office\'s converts into a password-protected zip, without high-confidentiality converts', function (): void {
    $open = documentsConvert($this->tenant, identity: '277006611');
    $secret = documentsConvert($this->tenant, identity: '277006622');
    ($this->in)(fn () => Convert::query()->whereKey($secret->id)->update(['full_name' => 'Secret Person', 'disclosure_risk_level' => DisclosureLevel::HighConfidentiality]));

    [$export, $password] = ($this->in)(fn () => app(Exports::class)->request(ExportType::Converts, [], 'Annual review', $this->admin));
    $export = ($this->in)(fn () => $export->refresh());

    expect($export)->status->toBe(ExportStatus::Ready)->row_count->toBe(1)
        ->and($export->expires_at->greaterThan(now()->addHours(23)))->toBeTrue()
        ->and(($this->unzip)($export, 'wrong-password'))->toBeFalse();

    $csv = (string) ($this->unzip)($export, $password);
    expect($csv)->toContain('Daniel Mendoza')->toContain($open->alias())->not->toContain('Secret Person')->not->toContain($secret->alias())
        ->and(Storage::disk('local')->get((string) $export->storage_path))->not->toContain('Daniel Mendoza');
});

it('asks a reason for personal data, keeps it out of Central and needs reports.export', function (): void {
    ($this->in)(fn () => expectRuleViolation(fn () => app(Exports::class)->request(ExportType::Converts, [], '', $this->admin), 'reason'));
    ($this->in)(fn () => expect(fn () => app(Exports::class)->request(ExportType::ShahadahStatistics, [], null, grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant)))
        ->toThrow(AuthorizationException::class));

    $central = grantRole(User::factory()->create(), 'central_reporting');
    expectRuleViolation(fn () => app(Exports::class)->request(ExportType::Converts, [], 'x', $central), 'export_type');
    [$national] = app(Exports::class)->request(ExportType::ShahadahStatistics, [], null, $central);
    expect($national->refresh()->tenant_id)->toBeNull()->and($national->status)->toBe(ExportStatus::Ready);
});

it('allows five exports an hour per user', function (): void {
    ($this->in)(function (): void {
        foreach (range(1, Exports::PER_HOUR) as $attempt) {
            app(Exports::class)->request(ExportType::ShahadahStatistics, [], null, $this->admin);
        }

        expectRuleViolation(fn () => app(Exports::class)->request(ExportType::ShahadahStatistics, [], null, $this->admin), 'export_type');
    });
});

it('lets only the requester download, counts and audits downloads, and purges expired files', function (): void {
    [$export] = ($this->in)(fn () => app(Exports::class)->request(ExportType::ShahadahStatistics, [], null, $this->admin));
    $other = grantRole(memberOf($this->tenant), 'center_admin', $this->tenant);

    ($this->in)(function () use ($export, $other): void {
        expect(fn () => app(Exports::class)->downloadUrl($export->refresh(), $other))->toThrow(AuthorizationException::class);
        $url = app(Exports::class)->downloadUrl($export->refresh(), $this->admin);
        $this->get($url)->assertOk();

        expect($export->refresh()->download_count)->toBe(1)
            ->and(AuditLog::query()->where('action', 'export.downloaded')->sole()->actor_id)->toBe($this->admin->id);
    });

    $this->travel(25)->hours();
    $this->artisan('exports:purge')->assertSuccessful();

    ($this->in)(function () use ($export): void {
        expect($export->refresh())->status->toBe(ExportStatus::Expired)->storage_path->toBeNull();
        expectRuleViolation(fn () => app(Exports::class)->downloadUrl($export, $this->admin), 'status');
    });
});
