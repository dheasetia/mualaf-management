<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Documents\Actions\DocumentLibrary;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Enums\VerificationStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Antivirus\FileScanner;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
    Storage::fake('local');
    config(['documents.antivirus.enabled' => true]);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create();
    $this->convert = documentsConvert($this->tenant);
    $this->uploader = grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant);
    $this->verifier = grantRole(memberOf($this->tenant), 'shahadah_verifier', $this->tenant);
    $this->scanner = new class implements FileScanner
    {
        public ?string $result = null;

        public bool $down = false;

        public function scan(string $bytes): ?string
        {
            if ($this->down) {
                throw new RuntimeException('clamd down');
            }

            return $this->result;
        }
    };
    $this->app->instance(FileScanner::class, $this->scanner);
    $this->upload = function (string $bytes = "%PDF-1.4\n%fake\n"): Document {
        $path = tempnam(sys_get_temp_dir(), 'av');
        file_put_contents($path, $bytes);

        return inTenant($this->tenant, fn () => app(DocumentLibrary::class)->upload($this->convert, DocumentType::IqamahCopy, $path, 'iqamah.pdf', $this->uploader));
    };
});

it('scans uploads and lets only clean files be verified and downloaded', function (): void {
    $document = ($this->upload)()->refresh();

    expect($document->scan_status)->toBe(ScanStatus::Clean);
    inTenant($this->tenant, fn () => app(DocumentLibrary::class)->verify($document, $this->verifier));
    expect($document->refresh()->verification_status)->toBe(VerificationStatus::Verified);
});

it('removes infected files and rejects the document', function (): void {
    $this->scanner->result = 'Eicar-Signature';

    $document = ($this->upload)()->refresh();

    expect($document)->scan_status->toBe(ScanStatus::Infected)->verification_status->toBe(VerificationStatus::Rejected)
        ->and(AuditLog::query()->where('action', 'document.infected')->exists())->toBeTrue();
    Storage::disk('local')->assertMissing($document->storage_path);
    inTenant($this->tenant, function () use ($document): void {
        expectRuleViolation(fn () => app(DocumentLibrary::class)->downloadUrl($document, $this->verifier), 'scan_status');
    });
});

it('keeps a file unusable while the scanner is unreachable', function (): void {
    $this->scanner->down = true;

    $document = ($this->upload)()->refresh();

    expect($document->scan_status)->not->toBe(ScanStatus::Clean)->and($document->scan_status->isSafe())->toBeFalse();
    inTenant($this->tenant, fn () => expectRuleViolation(fn () => app(DocumentLibrary::class)->verify($document, $this->verifier), 'scan_status'));
});
