<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Documents\Actions\DocumentLibrary;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\VerificationStatus;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
    // A real local disk (not Storage::fake) so signed temporary links work end to end.
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/documents')]);
    Storage::forgetDisk('local');
    File::deleteDirectory(storage_path('framework/testing/disks/documents'));
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->convert = documentsConvert($this->tenant);
    $this->uploader = grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant);
    $this->verifier = grantRole(memberOf($this->tenant), 'shahadah_verifier', $this->tenant);
    $this->library = fn (): DocumentLibrary => app(DocumentLibrary::class);
    $this->file = function (string $bytes): string {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $bytes);

        return $path;
    };
    $this->upload = fn (string $bytes = "%PDF-1.4\n%fake\n", ?User $actor = null) => inTenant($this->tenant, fn () => ($this->library)()
        ->upload($this->convert, DocumentType::IqamahCopy, ($this->file)($bytes), 'iqamah.pdf', $actor ?? $this->uploader));
});

it('stores an upload in the office and convert folder with a checksum and a first version', function (): void {
    $document = ($this->upload)();

    expect($document)
        ->storage_path->toBe("tenants/{$this->tenant->uuid}/converts/{$this->convert->uuid}/documents/{$document->uuid}/v1.pdf")
        ->mime_type->toBe('application/pdf')
        ->checksum->toBe(hash('sha256', "%PDF-1.4\n%fake\n"))
        ->verification_status->toBe(VerificationStatus::Pending)
        ->and(DocumentVersion::query()->where('document_id', $document->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'document.uploaded')->exists())->toBeTrue();
    Storage::disk('local')->assertExists($document->storage_path);
});

it('judges the file type by its content and limits the size', function (): void {
    expectRuleViolation(fn () => ($this->upload)('<?php echo 1;'), 'file');

    config(['documents.max_bytes' => 10]);
    expectRuleViolation(fn () => ($this->upload)("%PDF-1.4\n%a longer file\n"), 'file');
});

it('removes EXIF metadata such as location from images', function (): void {
    $jpeg = jpegWithExif('GPS-SECRET-LOCATION');
    expect($jpeg)->toContain('GPS-SECRET-LOCATION');

    $document = ($this->upload)($jpeg);
    $stored = Storage::disk('local')->get($document->storage_path);

    expect($document->mime_type)->toBe('image/jpeg')
        ->and($stored)->not->toContain('GPS-SECRET-LOCATION')
        ->and($stored)->not->toContain("Exif\x00\x00");
});

it('keeps every version and restarts verification for a replacement', function (): void {
    $document = ($this->upload)();
    inTenant($this->tenant, fn () => ($this->library)()->verify($document, $this->verifier));

    $document = inTenant($this->tenant, fn () => ($this->library)()->addVersion($document, ($this->file)("%PDF-1.4\n%second\n"), 'new.pdf', $this->uploader));

    expect($document)
        ->current_version->toBe(2)
        ->verification_status->toBe(VerificationStatus::Pending)
        ->storage_path->toEndWith('/v2.pdf')
        ->and(DocumentVersion::query()->where('document_id', $document->id)->pluck('version_no')->all())->toBe([1, 2]);
    Storage::disk('local')->assertExists(str_replace('/v2.pdf', '/v1.pdf', $document->storage_path));

    expect(fn () => DocumentVersion::query()->firstOrFail()->forceFill(['checksum' => str_repeat('0', 64)])->save())->toThrow(LogicException::class);
});

it('is verified once, by someone other than the uploader, and rejected only with a reason', function (): void {
    $document = ($this->upload)();
    $both = grantRole($this->uploader, 'shahadah_verifier', $this->tenant);

    inTenant($this->tenant, function () use ($document, $both): void {
        expectRuleViolation(fn () => ($this->library)()->verify($document, $both), 'verification_status');
        expectRuleViolation(fn () => ($this->library)()->reject($document, $this->verifier, ' '), 'reason');

        ($this->library)()->reject($document, $this->verifier, 'Unreadable scan');
        expectRuleViolation(fn () => ($this->library)()->verify($document, $this->verifier), 'verification_status');
    });

    expect($document->refresh())->verification_status->toBe(VerificationStatus::Rejected)->rejection_reason->toBe('Unreadable scan');
});

it('lets only holders of the document permissions with the full profile upload, verify or download', function (): void {
    $dai = grantRole(memberOf($this->tenant), 'dai', $this->tenant);

    expect(fn () => ($this->upload)(actor: $dai))->toThrow(AuthorizationException::class);

    $document = ($this->upload)();
    inTenant($this->tenant, function () use ($document, $dai): void {
        expect(fn () => ($this->library)()->verify($document, $this->uploader))->toThrow(AuthorizationException::class)
            ->and(fn () => ($this->library)()->downloadUrl($document, $dai))->toThrow(AuthorizationException::class);
    });
});

it('hands out a short-lived signed link and audits the download', function (): void {
    $document = ($this->upload)();

    $url = inTenant($this->tenant, fn () => ($this->library)()->downloadUrl($document, $this->verifier));

    expect(AuditLog::query()->where('action', 'document.downloaded')->sole()->actor_id)->toBe($this->verifier->id);

    $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(preg_replace('/signature=\w+/', 'signature=forged', $url))->assertForbidden();

    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
});
