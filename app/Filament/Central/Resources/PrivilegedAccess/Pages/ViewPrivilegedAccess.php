<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\PrivilegedAccess\Pages;

use App\Domain\Privileged\Actions\PrivilegedAccess;
use App\Domain\Privileged\Enums\PrivilegedScope;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Filament\Central\Resources\PrivilegedAccess\PrivilegedAccessResource;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Read-only view of an active grant for its requester; every load is audited.
 */
final class ViewPrivilegedAccess extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PrivilegedAccessResource::class;

    protected string $view = 'filament.central.privileged-view';

    /** @var array<string, array<string, string|null>> */
    public array $sections = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $user = auth()->user();
        $grant = $this->getRecord();

        try {
            $this->sections = $grant instanceof PrivilegedAccessRequest && $user instanceof User ? app(PrivilegedAccess::class)->open($grant, $user) : [];
        } catch (AuthorizationException|RuleViolation) {
            abort(403);
        }
    }

    public function getTitle(): string
    {
        return __('privileged.model');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'labels' => collect(PrivilegedScope::cases())->mapWithKeys(fn (PrivilegedScope $scope): array => [$scope->value => $scope->label()])->all(),
            'fieldLabels' => (array) __('privileged.view_fields'),
        ];
    }
}
