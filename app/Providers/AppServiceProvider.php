<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Policies\AuditLogPolicy;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Policies\DaiPolicy;
use App\Domain\Identity\Listeners\RecordFailedLogin;
use App\Domain\Identity\Listeners\RecordSuccessfulLogin;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Identity\Rbac\PermissionCatalog;
use App\Domain\Platform\Models\BudgetCategory;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Region;
use App\Domain\Platform\Models\Religion;
use App\Domain\Platform\Policies\MasterDataPolicy;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // State lives in Laravel Context, so one instance per application is safe.
        $this->app->singleton(TenantContext::class);
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

        foreach ([Region::class, City::class, Country::class, Language::class, Religion::class, BudgetCategory::class, DaiActivityType::class] as $masterModel) {
            Gate::policy($masterModel, MasterDataPolicy::class);
        }

        Event::listen(Failed::class, RecordFailedLogin::class);
        Event::listen(Login::class, RecordSuccessfulLogin::class);
    }
}
