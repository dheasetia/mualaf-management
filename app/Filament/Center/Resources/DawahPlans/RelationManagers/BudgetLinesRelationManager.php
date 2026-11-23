<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans\RelationManagers;

use App\Domain\Dawah\Actions\PlanBudget;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DawahActivityBudget;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Platform\Models\BudgetCategory;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Budget lines of a plan: estimates by its da'i, approved amounts by
 * budget.approve, actuals by budget.record_actual or the reporting da'i.
 */
final class BudgetLinesRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'budgetLines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('dawah.budget.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $ownerRecord instanceof DawahActivityPlan && $user instanceof User
            && ($user->hasPermission('budget.view') || app(DawahActors::class)->isPlanDai($user, $ownerRecord));
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $plan = $this->getOwnerRecord();
        $user = fn (): ?User => ($user = auth()->user()) instanceof User ? $user : null;
        $status = fn (): ?PlanStatus => $plan instanceof DawahActivityPlan ? DawahActivityPlan::query()->whereKey($plan->id)->value('status') : null;
        $isPlanDai = fn (): bool => ($u = $user()) !== null && $plan instanceof DawahActivityPlan && $u->hasPermission('dawah.plans.create') && app(DawahActors::class)->isPlanDai($u, $plan);
        $editable = fn (): bool => $isPlanDai() && ($status()?->isEditable() ?? false);
        $budget = fn (): PlanBudget => app(PlanBudget::class);
        $money = fn (string $column, string $label): TextColumn => TextColumn::make($column)->label(__('dawah.budget_fields.'.$label))->numeric(2)->placeholder('—');
        $lineForm = [
            Select::make('budget_category_id')->label(__('dawah.budget_fields.category'))->required()
                ->options(fn (): array => LocalizedName::options(BudgetCategory::query()->where('active', true))),
            TextInput::make('description')->label(__('dawah.budget_fields.description'))->required()->maxLength(255),
            TextInput::make('quantity')->label(__('dawah.budget_fields.quantity'))->required()->numeric()->minValue(0.01),
            TextInput::make('unit_cost')->label(__('dawah.budget_fields.unit_cost'))->required()->numeric()->minValue(0),
            Textarea::make('notes')->label(__('dawah.budget_fields.notes'))->maxLength(1000),
        ];
        $lineData = fn (array $data): array => [
            'budget_category_id' => (int) $data['budget_category_id'],
            'description' => (string) $data['description'],
            'quantity' => (string) $data['quantity'],
            'unit_cost' => (string) $data['unit_cost'],
            'notes' => blank($data['notes'] ?? null) ? null : (string) $data['notes'],
        ];

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->columns([
                TextColumn::make('category.'.LocalizedName::column())->label(__('dawah.budget_fields.category')),
                TextColumn::make('description')->label(__('dawah.budget_fields.description'))->limit(40),
                TextColumn::make('quantity')->label(__('dawah.budget_fields.quantity'))->numeric(2),
                $money('unit_cost', 'unit_cost'),
                $money('estimated_amount', 'estimated_amount')->summarize(Sum::make()->numeric(2)),
                $money('approved_amount', 'approved_amount')->summarize(Sum::make()->numeric(2)),
                $money('actual_amount', 'actual_amount')->summarize(Sum::make()->numeric(2)),
                IconColumn::make('receipt')->label(__('dawah.budget_fields.receipt'))->boolean()
                    ->state(fn (DawahActivityBudget $record): bool => $record->receipt_document_id !== null),
                $money('variance_amount', 'variance_amount')
                    ->color(fn (DawahActivityBudget $record): string => (float) $record->variance_amount > 0 && $record->actual_amount !== null ? 'danger' : 'gray'),
            ])
            ->headerActions([
                Action::make('addLine')->label(__('dawah.budget_actions.add_line'))->visible($editable)->schema($lineForm)
                    ->action(self::domainAction(fn (mixed $record, array $data, User $actor) => $budget()->saveLine($plan instanceof DawahActivityPlan ? $plan : abort(404), null, $lineData($data), $actor), 'dawah.notifications.saved')),
            ])
            ->recordActions([
                Action::make('editLine')->label(__('filament-actions::edit.single.label'))->visible($editable)->schema($lineForm)
                    ->fillForm(fn (DawahActivityBudget $record): array => $record->only(['budget_category_id', 'description', 'quantity', 'unit_cost', 'notes']))
                    ->action(self::domainAction(fn (DawahActivityBudget $record, array $data, User $actor) => $budget()->saveLine($plan instanceof DawahActivityPlan ? $plan : abort(404), $record, $lineData($data), $actor), 'dawah.notifications.saved')),
                Action::make('removeLine')->label(__('filament-actions::delete.single.label'))->color('danger')->requiresConfirmation()->visible($editable)
                    ->action(self::domainAction(fn (DawahActivityBudget $record, array $data, User $actor) => $budget()->removeLine($record, $actor), 'dawah.notifications.saved')),
                Action::make('approveAmount')->label(__('dawah.budget_actions.approve_amount'))->color('success')
                    ->visible(fn (): bool => ($user()?->hasPermission('budget.approve') ?? false) && ! $isPlanDai() && in_array($status(), [PlanStatus::Submitted, PlanStatus::SupervisorReview], true))
                    ->fillForm(fn (DawahActivityBudget $record): array => ['amount' => $record->approved_amount ?? $record->estimated_amount])
                    ->schema([TextInput::make('amount')->label(__('dawah.budget_fields.approved_amount'))->required()->numeric()->minValue(0)])
                    ->action(self::domainAction(fn (DawahActivityBudget $record, array $data, User $actor) => $budget()->approveLine($record, (string) $data['amount'], $actor), 'dawah.notifications.saved')),
                Action::make('attachReceipt')->label(__('dawah.budget_actions.attach_receipt'))->icon('heroicon-o-paper-clip')
                    ->visible(fn (): bool => in_array($status(), [PlanStatus::InProgress, PlanStatus::Completed], true)
                        && (($user()?->hasPermission('budget.record_actual') ?? false) || (($u = $user()) !== null && $plan instanceof DawahActivityPlan && $u->hasPermission('dawah.reports.submit') && app(DawahActors::class)->isResponsible($u, $plan))))
                    ->schema([
                        FileUpload::make('file')->label(__('dawah.budget_fields.receipt'))->required()->storeFiles(false)
                            ->maxSize((int) (config('documents.max_bytes') / 1024))
                            ->acceptedFileTypes(array_keys((array) config('documents.allowed_mime_types'))),
                    ])
                    ->action(self::domainAction(function (DawahActivityBudget $record, array $data, User $actor) use ($budget): void {
                        $file = $data['file'] ?? null;
                        $file instanceof TemporaryUploadedFile ? $budget()->attachReceipt($record, $file->getRealPath(), $file->getClientOriginalName(), $actor) : abort(422);
                    }, 'dawah.notifications.saved')),
                Action::make('downloadReceipt')->label(__('dawah.budget_actions.download_receipt'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (DawahActivityBudget $record): bool => $record->receipt_document_id !== null)
                    ->action(function (DawahActivityBudget $record) use ($budget, $user): void {
                        $this->redirect($budget()->receiptUrl($record, $user() ?? abort(403)));
                    }),
                Action::make('recordActual')->label(__('dawah.budget_actions.record_actual'))
                    ->visible(fn (): bool => in_array($status(), [PlanStatus::InProgress, PlanStatus::Completed], true)
                        && (($user()?->hasPermission('budget.record_actual') ?? false) || (($u = $user()) !== null && $plan instanceof DawahActivityPlan && $u->hasPermission('dawah.reports.submit') && app(DawahActors::class)->isResponsible($u, $plan))))
                    ->fillForm(fn (DawahActivityBudget $record): array => ['amount' => $record->actual_amount, 'notes' => $record->notes])
                    ->schema([
                        TextInput::make('amount')->label(__('dawah.budget_fields.actual_amount'))->required()->numeric()->minValue(0),
                        Textarea::make('notes')->label(__('dawah.budget_fields.notes'))->maxLength(1000),
                    ])
                    ->action(self::domainAction(fn (DawahActivityBudget $record, array $data, User $actor) => $budget()->recordActual($record, (string) $data['amount'], $data['notes'] ?? null, $actor), 'dawah.notifications.saved')),
            ]);
    }
}
