<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Support\LocaleSwitcher;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\SetLocale;
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

class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('central')
            ->path('central')
            ->login(Login::class)
            ->userMenuItems([LocaleSwitcher::userMenuItem()])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => view('filament.auth.locale-switcher')->render())
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Central/Resources'), for: 'App\Filament\Central\Resources')
            ->discoverPages(in: app_path('Filament/Central/Pages'), for: 'App\Filament\Central\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Central/Widgets'), for: 'App\Filament\Central\Widgets')
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
            ]);
    }
}
