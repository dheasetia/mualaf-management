<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Identity\Services\AccessReview;
use App\Support\Tenancy\TenantContext;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Office access review for the Local Auditor and Center Admin (audit.view_office).
 */
final class AccessReviewPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $slug = 'access-review';

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.security.access-review';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('audit.view_office') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.access_review.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('reports.navigation_group');
    }

    public function getTitle(): string
    {
        return __('identity.access_review.title');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['rows' => app(AccessReview::class)->rows(app(TenantContext::class)->requireId()), 'days' => (int) config('security.stale_account_days')];
    }
}
