<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Checklists\RelationManagers;

use App\Domain\International\Actions\Checklists;
use App\Domain\International\Enums\ChecklistItemCode;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Enums\ItemStatus;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Domain\International\Models\PreDepartureChecklistItem;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class ItemsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('international.checklists.model');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $checklist = $this->getOwnerRecord();
        $open = fn (): bool => $checklist instanceof PreDepartureChecklist && PreDepartureChecklist::query()->whereKey($checklist->id)->value('status') === ChecklistStatus::Open
            && (auth()->user()?->hasPermission('followups.create') ?? false);
        $mark = fn (string $name, ItemStatus $status): Action => Action::make($name)->label(__('international.actions.'.$name))->visible($open)
            ->schema([TextInput::make('notes')->label(__('international.fields.notes'))->maxLength(1000)])
            ->action(self::domainAction(fn (PreDepartureChecklistItem $record, array $data, User $user) => app(Checklists::class)->mark($record, $status, $data['notes'] ?? null, $user), 'international.notifications.saved'));

        return $table
            ->columns([
                TextColumn::make('item_code')->label(__('international.fields.item'))->formatStateUsing(fn (ChecklistItemCode $state): string => $state->label()),
                TextColumn::make('status')->label(__('international.fields.status'))->badge()->formatStateUsing(fn (ItemStatus $state): string => $state->label())
                    ->color(fn (ItemStatus $state): string => $state === ItemStatus::Pending ? 'warning' : 'success'),
                TextColumn::make('notes')->label(__('international.fields.notes'))->placeholder('—'),
            ])
            ->recordActions([
                $mark('done', ItemStatus::Done)->color('success'),
                $mark('not_applicable', ItemStatus::NotApplicable)->color('gray'),
                $mark('reset', ItemStatus::Pending)->color('warning'),
            ]);
    }
}
