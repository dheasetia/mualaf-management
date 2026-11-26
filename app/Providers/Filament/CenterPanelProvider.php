<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Center\Pages\OfficeProfile;
use App\Filament\Support\Brand;
use App\Filament\Support\LocaleSwitcher;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\RequireMfaForPrivilegedRoles;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantContext;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CenterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return Brand::apply($panel)
            ->id('center')
            ->path('center')
            ->login(Login::class)
            ->userMenuItems([
                // Inside an office, open the profile in the panel layout (sidebar kept).
                'profile' => fn (Action $action): Action => $action->url(fn (): string => Filament::getTenant() !== null ? OfficeProfile::getUrl() : (string) Filament::getProfileUrl()),
                LocaleSwitcher::userMenuItem(),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => view('filament.auth.locale-switcher')->render())
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->passwordReset()
            ->profile(EditProfile::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: false)
            ->tenant(Tenant::class, slugAttribute: 'code')
            ->tenantMiddleware([
                SetTenantContext::class,
            ], isPersistent: true)
            ->navigationGroups(self::navigationGroups())
            ->discoverResources(in: app_path('Filament/Center/Resources'), for: 'App\Filament\Center\Resources')
            ->discoverPages(in: app_path('Filament/Center/Pages'), for: 'App\Filament\Center\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Center/Widgets'), for: 'App\Filament\Center\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SetLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforceAbsoluteSessionLifetime::class,
                RequireMfaForPrivilegedRoles::class,
            ]);
    }

    /**
     * Sidebar group order. Labels are closures so they follow the request locale
     * and match the groups the resources and pages declare.
     *
     * @return list<NavigationGroup>
     */
    private static function navigationGroups(): array
    {
        return array_map(
            fn (string $key): NavigationGroup => NavigationGroup::make(fn (): string => __($key)),
            [
                'dawah.navigation_group',
                'guidance.navigation_group',
                'submissions.navigation_group',
                'reports.navigation_group',
                'transport.navigation_group',
                'pilgrimage.navigation_group',
                'international.navigation_group',
                'audit.navigation_group',
            ],
        );
    }
}
