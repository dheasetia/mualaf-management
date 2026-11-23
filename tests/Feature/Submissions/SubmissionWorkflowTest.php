<?php

declare(strict_types=1);

use App\Domain\Administration\Actions\SubmissionWorkflow;
use App\Domain\Administration\Enums\DocumentCheck;
use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionEvent;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\SubmissionTypeSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
    $this->seed([DocumentTemplateSeeder::class, SubmissionTypeSeeder::class]);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    Storage::fake('local');
    Queue::fake();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->clerk = grantRole(memberOf($this->tenant), 'administrative_officer', $this->tenant);
    $this->approver = grantRole(memberOf($this->tenant), 'approver', $this->tenant);
    $this->convert = documentsConvert($this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->workflow = fn (): SubmissionWorkflow => app(SubmissionWorkflow::class);
    $this->pdf = function (string $content = "%PDF-1.4\n%iqamah\n"): string {
        $path = tempnam(sys_get_temp_dir(), 'sub');
        file_put_contents($path, $content);

        return $path;
    };
    $this->create = fn (string $type = 'religion_update') => ($this->in)(fn () => ($this->workflow)()->create(
        $this->convert, SubmissionType::query()->where('code', $type)->sole(), 'Needed for the civil registry', $this->clerk,
    ));
    $this->toVerification = function (Submission $submission): Submission {
        return ($this->in)(function () use ($submission): Submission {
            ($this->workflow)()->attach($submission, 'iqamah_copy', ($this->pdf)(), 'iqamah.pdf', $this->clerk);
            ($this->workflow)()->submit($submission, $this->clerk);

            return ($this->workflow)()->startVerification($submission, $this->clerk);
        });
    };
    $this->verifyAll = fn (Submission $submission) => ($this->in)(fn () => $submission->documents()->get()
        ->each(fn ($document) => ($this->workflow)()->checkDocument($document, DocumentCheck::Verified, null, $this->clerk)));
});

it('takes a submission from draft to completed and issues its letter', function (): void {
    $submission = ($this->toVerification)(($this->create)());
    ($this->verifyAll)($submission);

    ($this->in)(function () use ($submission): void {
        ($this->workflow)()->approve($submission, $this->approver);
        ($this->workflow)()->startProcessing($submission, $this->clerk);
        ($this->workflow)()->complete($submission, 'Civil Affairs, Dammam', $this->clerk);
    });

    $submission = ($this->in)(fn () => $submission->refresh());
    $letter = ($this->in)(fn () => GeneratedDocument::query()->findOrFail($submission->generated_document_id));

    expect($submission)
        ->reference_no->toBe('DMM-SUB-'.now()->format('Y').'-000001')
        ->status->toBe(SubmissionStatus::Completed)
        ->due_at->not->toBeNull()
        ->and($letter->snapshot_data['addressee'])->toBe('Civil Affairs, Dammam')
        ->and(($this->in)(fn () => SubmissionEvent::query()->where('submission_id', $submission->id)->where('event_type', 'status_changed')->count()))->toBe(5);
});

it('submits only with every required document, and locks documents once submitted', function (): void {
    $submission = ($this->create)();

    ($this->in)(function () use ($submission): void {
        expectRuleViolation(fn () => ($this->workflow)()->submit($submission, $this->clerk), 'status');
        expectRuleViolation(fn () => ($this->workflow)()->attach($submission, 'passport_scan', ($this->pdf)(), 'x.pdf', $this->clerk), 'requirement_code');

        ($this->workflow)()->attach($submission, 'iqamah_copy', ($this->pdf)(), 'iqamah.pdf', $this->clerk);
        ($this->workflow)()->submit($submission, $this->clerk);
        expectRuleViolation(fn () => ($this->workflow)()->attach($submission, 'iqamah_copy', ($this->pdf)(), 'again.pdf', $this->clerk), 'file');
    });
});

it('approves only verified submissions, never by the creator, with a different person per stage', function (): void {
    ($this->in)(fn () => SubmissionType::query()->where('code', 'religion_update')->update(['workflow_config' => json_encode(['approval_stages' => 2, 'sla_days' => 5])]));
    $submission = ($this->toVerification)(($this->create)());

    ($this->in)(function () use ($submission): void {
        expectRuleViolation(fn () => ($this->workflow)()->approve($submission, $this->approver), 'status');
    });
    ($this->verifyAll)($submission);

    ($this->in)(function () use ($submission): void {
        expect(fn () => ($this->workflow)()->approve($submission, $this->clerk))->toThrow(AuthorizationException::class);
        expectRuleViolation(fn () => ($this->workflow)()->approve($submission, grantRole($this->clerk, 'approver', $this->tenant)), 'status');

        ($this->workflow)()->approve($submission, $this->approver);
        expect($submission->refresh()->status)->toBe(SubmissionStatus::UnderVerification);
        expectRuleViolation(fn () => ($this->workflow)()->approve($submission, $this->approver), 'status');

        ($this->workflow)()->approve($submission, grantRole(memberOf($this->tenant), 'approver', $this->tenant));
        expect($submission->refresh()->status)->toBe(SubmissionStatus::Approved);
    });
});

it('sends a submission back for correction and restarts approvals after resubmission', function (): void {
    ($this->in)(fn () => SubmissionType::query()->where('code', 'religion_update')->update(['workflow_config' => json_encode(['approval_stages' => 2])]));
    $submission = ($this->toVerification)(($this->create)());
    ($this->verifyAll)($submission);

    ($this->in)(function () use ($submission): void {
        ($this->workflow)()->approve($submission, $this->approver);
        expectRuleViolation(fn () => ($this->workflow)()->requestCorrection($submission, $this->approver, ''), 'reason');
        ($this->workflow)()->requestCorrection($submission, $this->approver, 'The scan is cut off');

        $this->travel(1)->minutes();
        ($this->workflow)()->attach($submission, 'iqamah_copy', ($this->pdf)("%PDF-1.4\n%better\n"), 'iqamah-2.pdf', $this->clerk);
        ($this->workflow)()->submit($submission, $this->clerk);
        ($this->workflow)()->startVerification($submission, $this->clerk);
    });
    ($this->verifyAll)($submission);

    ($this->in)(function () use ($submission): void {
        ($this->workflow)()->approve($submission, $this->approver);
        expect($submission->refresh()->status)->toBe(SubmissionStatus::UnderVerification);
    });
});

it('rejects with a reason and lets only the creator cancel early or an approver cancel later', function (): void {
    $submission = ($this->create)();
    $otherClerk = grantRole(memberOf($this->tenant), 'administrative_officer', $this->tenant);

    ($this->in)(function () use ($submission, $otherClerk): void {
        expect(fn () => ($this->workflow)()->cancel($submission, $otherClerk, 'Duplicate'))->toThrow(AuthorizationException::class);
        ($this->workflow)()->cancel($submission, $this->clerk, 'Duplicate');
    });

    $second = ($this->toVerification)(($this->create)());
    ($this->in)(function () use ($second): void {
        expectRuleViolation(fn () => ($this->workflow)()->reject($second, $this->approver, ''), 'reason');
        ($this->workflow)()->reject($second, $this->approver, 'Not eligible');
        expect($second->refresh()->status)->toBe(SubmissionStatus::Rejected);
    });
});

it('keeps submission files off the convert\'s documents and behind submissions.view', function (): void {
    $submission = ($this->create)('khitan_referral');

    $attached = ($this->in)(fn () => ($this->workflow)()->attach($submission, 'medical_letter', ($this->pdf)("%PDF-1.4\n%medical\n"), 'medical.pdf', $this->clerk));

    ($this->in)(function () use ($attached): void {
        expect($this->convert->documents()->count())->toBe(0)
            ->and(($this->workflow)()->documentUrl($attached, $this->approver))->toContain('/submissions/');
        expect(fn () => ($this->workflow)()->documentUrl($attached, grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant)))->toThrow(AuthorizationException::class);
    });
});

it('keeps the submission history append-only', function (): void {
    $submission = ($this->create)();

    ($this->in)(fn () => expect(fn () => SubmissionEvent::query()->where('submission_id', $submission->id)->firstOrFail()->forceFill(['reason' => 'x'])->save())->toThrow(LogicException::class));
});
