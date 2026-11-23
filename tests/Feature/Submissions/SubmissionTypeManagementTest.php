<?php

declare(strict_types=1);

use App\Domain\Administration\Actions\ManageSubmissionTypes;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\SubmissionTypes\Pages\ManageSubmissionTypesPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->admin = withAuthenticator(grantRole(User::factory()->create(), 'central_admin'));
    $this->data = fn (array $overrides = []): array => [
        'code' => 'zakat_letter', 'name_ar' => 'خطاب الزكاة', 'approval_stages' => 2, 'sla_days' => 10, 'active' => true,
        'generates_document_template_code' => 'other_letter',
        'required_documents' => [['code' => 'iqamah_copy', 'name_ar' => 'صورة الإقامة', 'required' => true], ['code' => 'salary_slip', 'name_ar' => 'مسير راتب', 'required' => false]],
        ...$overrides,
    ];
});

it('creates a submission type from Central with its documents and workflow', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::test(ManageSubmissionTypesPage::class)->callAction('create', ($this->data)())->assertHasNoActionErrors();

    $type = SubmissionType::query()->where('code', 'zakat_letter')->sole();
    expect($type->approvalStages())->toBe(2)
        ->and($type->slaDays())->toBe(10)
        ->and($type->requiredCodes())->toBe(['iqamah_copy'])
        ->and($type->requirementOptions())->toHaveKeys(['iqamah_copy', 'salary_slip']);
});

it('refuses bad codes, duplicate requirements, certificate letters and other people\'s changes', function (): void {
    $types = app(ManageSubmissionTypes::class);

    expectRuleViolation(fn () => $types->save(null, ($this->data)(['code' => 'Bad Code']), $this->admin), 'code');
    expectRuleViolation(fn () => $types->save(null, ($this->data)(['required_documents' => [['code' => 'x1', 'name_ar' => 'a'], ['code' => 'x1', 'name_ar' => 'b']]]), $this->admin), 'required_documents');
    expectRuleViolation(fn () => $types->save(null, ($this->data)(['generates_document_template_code' => 'shahadah_certificate']), $this->admin), 'generates_document_template_code');
    expect(fn () => $types->save(null, ($this->data)(), grantRole(User::factory()->create(), 'central_auditor')))->toThrow(AuthorizationException::class);

    $types->save(null, ($this->data)(), $this->admin);
    expectRuleViolation(fn () => $types->save(null, ($this->data)(), $this->admin), 'code');
});

it('keeps the code of a type that submissions already use', function (): void {
    $type = app(ManageSubmissionTypes::class)->save(null, ($this->data)(), $this->admin);
    $tenant = Tenant::factory()->active()->create();
    inTenant($tenant, fn () => Submission::factory()->create(['submission_type_id' => $type->id]));

    expectRuleViolation(fn () => app(ManageSubmissionTypes::class)->save($type, ($this->data)(['code' => 'renamed']), $this->admin), 'code');
    expect(app(ManageSubmissionTypes::class)->save($type, ($this->data)(['name_ar' => 'اسم جديد', 'active' => false]), $this->admin))
        ->name_ar->toBe('اسم جديد')->active->toBeFalse();
});
