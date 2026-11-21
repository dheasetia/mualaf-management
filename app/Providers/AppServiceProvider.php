<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Listeners\RecordFailedLogin;
use App\Domain\Identity\Listeners\RecordSuccessfulLogin;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Identity\Rbac\PermissionCatalog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
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

        Event::listen(Failed::class, RecordFailedLogin::class);
        Event::listen(Login::class, RecordSuccessfulLogin::class);
    }
}
