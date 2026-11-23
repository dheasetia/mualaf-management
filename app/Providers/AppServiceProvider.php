<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Policies\SubmissionPolicy;
use App\Domain\Audit\Listeners\RecordTenantScopeBypass;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Policies\AuditLogPolicy;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Certificates\Policies\CertificatePolicy;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Policies\CompanyPolicy;
use App\Domain\Converts\Contracts\TeacherRoster;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Policies\ConvertPolicy;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Policies\DaiPolicy;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Dawah\Models\DawahMaterialDistribution;
use App\Domain\Dawah\Policies\DaiDailyActivityPolicy;
use App\Domain\Dawah\Policies\DawahActivityPlanPolicy;
use App\Domain\Dawah\Policies\DawahMaterialDistributionPolicy;
use App\Domain\Dawah\Policies\DawahMaterialPolicy;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Policies\DocumentTemplatePolicy;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Policies\ExamPolicy;
use App\Domain\FollowUp\Events\ConvertReturnedHome;
use App\Domain\FollowUp\Listeners\ScheduleFollowUps;
use App\Domain\Guidance\Events\EnrollmentCompleted;
use App\Domain\Guidance\Listeners\CloseEnrollmentsOnTransfer;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Policies\GuidancePolicies;
use App\Domain\Guidance\Services\GuidanceTeacherRoster;
use App\Domain\Identity\Listeners\RecordFailedLogin;
use App\Domain\Identity\Listeners\RecordSuccessfulLogin;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Identity\Rbac\PermissionCatalog;
use App\Domain\International\Listeners\OpenChecklistOnReturnHome;
use App\Domain\International\Models\ConsentRecord;
use App\Domain\International\Models\ConvertHomeCountryContact;
use App\Domain\International\Models\ConvertHomeLocation;
use App\Domain\International\Models\ExternalIslamicOrganization;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Domain\International\Policies\InternationalPolicy;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Pilgrimage\Policies\PilgrimagePolicy;
use App\Domain\Platform\Models\BudgetCategory;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Region;
use App\Domain\Platform\Models\Religion;
use App\Domain\Platform\Policies\MasterDataPolicy;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Policies\ProspectPolicy;
use App\Domain\Registration\Events\ShahadahVerified;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Domain\Registration\Policies\ConvertRegistrationPolicy;
use App\Domain\Registration\Policies\DuplicateReviewCasePolicy;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Events\TransferCompleted;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Models\ProspectTransferRequest;
use App\Domain\Transfers\Policies\ConvertTransferRequestPolicy;
use App\Domain\Transfers\Policies\ProspectTransferRequestPolicy;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportRequest;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Policies\TransportPolicy;
use App\Models\User;
use App\Support\Pdf\GotenbergPdfRenderer;
use App\Support\Pdf\PdfRenderer;
use App\Support\Tenancy\Events\TenantScopeBypassed;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // State lives in Laravel Context, so one instance per application is safe.
        $this->app->singleton(TenantContext::class);
        $this->app->bind(TeacherRoster::class, GuidanceTeacherRoster::class);
        $this->app->bind(PdfRenderer::class, GotenbergPdfRenderer::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // 05-database-conventions §5.5: short aliases, and no class names in the database.
        Relation::enforceMorphMap([
            'user' => User::class,
            'tenant' => Tenant::class,
            'dai' => Dai::class,
            'prospect' => Prospect::class,
            'convert' => Convert::class,
            'company' => Company::class,
            'enrollment' => Enrollment::class,
            'certificate' => Certificate::class,
            'document' => Document::class,
            'generated_document' => GeneratedDocument::class,
            'dawah_plan' => DawahActivityPlan::class,
            'dai_activity' => DaiDailyActivity::class,
            'dawah_material' => DawahMaterial::class,
            'vehicle' => Vehicle::class,
            'driver' => Driver::class,
            'activity_transport' => ActivityTransportRequest::class,
            'pickup' => TransportRequest::class,
            'trip' => TransportTrip::class,
            'submission' => Submission::class,
            'pilgrimage_program' => PilgrimageProgram::class,
            'pilgrimage_registration' => PilgrimageRegistration::class,
            'consent' => ConsentRecord::class,
            'referral' => InternationalReferral::class,
            'checklist' => PreDepartureChecklist::class,
            'home_contact' => ConvertHomeCountryContact::class,
            'home_location' => ConvertHomeLocation::class,
        ]);

        // 13-infrastructure-security §13.5: at least 12 characters; leaked-password
        // check (Have I Been Pwned range API) only where outbound calls are expected.
        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Catalog permission codes are answered by RBAC; any other ability falls
        // through to policies. Not a bypass: Super Admin gets no implicit "yes".
        Gate::before(function (mixed $user, string $ability): ?bool {
            if (! $user instanceof User || ! PermissionCatalog::exists($ability)) {
                return null;
            }

            return $this->app->make(Authorizer::class)->check($user, $ability);
        });

        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Dai::class, DaiPolicy::class);
        Gate::policy(Prospect::class, ProspectPolicy::class);
        Gate::policy(Convert::class, ConvertPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::policy(Certificate::class, CertificatePolicy::class);
        Gate::policy(CertificateTemplate::class, CertificatePolicy::class);
        Gate::policy(DocumentTemplate::class, DocumentTemplatePolicy::class);

        RateLimiter::for('certificate-verification', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));

        foreach ([GuidanceProgram::class, GuidanceClass::class, Enrollment::class] as $guidanceModel) {
            Gate::policy($guidanceModel, GuidancePolicies::class);
        }
        Gate::policy(ConvertRegistration::class, ConvertRegistrationPolicy::class);
        Gate::policy(DuplicateReviewCase::class, DuplicateReviewCasePolicy::class);
        Gate::policy(ConvertTransferRequest::class, ConvertTransferRequestPolicy::class);
        Gate::policy(ProspectTransferRequest::class, ProspectTransferRequestPolicy::class);
        Gate::policy(DawahActivityPlan::class, DawahActivityPlanPolicy::class);
        Gate::policy(DaiDailyActivity::class, DaiDailyActivityPolicy::class);
        Gate::policy(DawahMaterial::class, DawahMaterialPolicy::class);
        Gate::policy(DawahMaterialDistribution::class, DawahMaterialDistributionPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);

        foreach ([InternationalReferral::class, PreDepartureChecklist::class, ExternalIslamicOrganization::class] as $internationalModel) {
            Gate::policy($internationalModel, InternationalPolicy::class);
        }
        Gate::policy(PilgrimageProgram::class, PilgrimagePolicy::class);
        Gate::policy(PilgrimageRegistration::class, PilgrimagePolicy::class);

        foreach ([Vehicle::class, Driver::class, ActivityTransportRequest::class, TransportRequest::class, TransportTrip::class] as $transportModel) {
            Gate::policy($transportModel, TransportPolicy::class);
        }

        foreach ([Region::class, City::class, Country::class, Language::class, Religion::class, BudgetCategory::class, DaiActivityType::class] as $masterModel) {
            Gate::policy($masterModel, MasterDataPolicy::class);
        }

        Event::listen(Failed::class, RecordFailedLogin::class);
        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Event::listen(TenantScopeBypassed::class, RecordTenantScopeBypass::class);
        Event::listen(TransferCompleted::class, CloseEnrollmentsOnTransfer::class);
        Event::listen(ConvertReturnedHome::class, OpenChecklistOnReturnHome::class);
        Event::listen([ShahadahVerified::class, EnrollmentCompleted::class, TransferCompleted::class, ConvertReturnedHome::class], ScheduleFollowUps::class);
    }
}
