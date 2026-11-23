<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Support\LocaleSwitcher;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\RequireMfaForPrivilegedRoles;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantContext;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
        return $panel
            ->id('center')
            ->path('center')
            ->login(Login::class)
            ->userMenuItems([LocaleSwitcher::userMenuItem()])
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
            ->colors([
                'primary' => Color::Teal,
            ])
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
}
