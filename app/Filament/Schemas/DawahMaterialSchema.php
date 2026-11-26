<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Domain\Dawah\Actions\DistributeMaterial;
use App\Domain\Dawah\Actions\ManageMaterials;
use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Enums\MaterialType;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Platform\Models\Language;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Localization\LocalizedName;
use App\Support\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Dawah material form and table shared by the Center (office materials, with
 * stock and distribution) and Central (national materials) screens (08 §8.4).
 */
final class DawahMaterialSchema
{
    use RunsDomainActions;

    /** @return list<Component> */
    public static function form(): array
    {
        return [
            Section::make(__('dawah.materials.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('title')->label(__('dawah.material_fields.title'))->required()->maxLength(255),
                    TextInput::make('title_ar')->label(__('dawah.material_fields.title_ar'))->maxLength(255),
                    Select::make('material_type')->label(__('dawah.material_fields.material_type'))->required()
                        ->options(collect(MaterialType::cases())->mapWithKeys(fn (MaterialType $type): array => [$type->value => $type->label()])->all()),
                    Select::make('language_id')->label(__('dawah.material_fields.language'))->required()->searchable()
                        ->options(fn (): array => LocalizedName::options(Language::query())),
                ]),
                Fieldset::make(__('forms.details'))->columns(2)->schema([
                    TextInput::make('topic')->label(__('dawah.material_fields.topic'))->maxLength(255),
                    TextInput::make('target_audience')->label(__('dawah.material_fields.target_audience'))->maxLength(255),
                    TextInput::make('external_url')->label(__('dawah.material_fields.external_url'))->url()->maxLength(500),
                    TextInput::make('stock_quantity')->label(__('dawah.material_fields.stock_quantity'))->integer()->minValue(0)
                        ->visible(fn (): bool => app(TenantContext::class)->has()),
                    Textarea::make('description')->label(__('dawah.material_fields.description'))->maxLength(5000)->columnSpanFull(),
                ]),
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        $manage = fn (): ManageMaterials => app(ManageMaterials::class);
        $user = fn (): ?User => ($user = auth()->user()) instanceof User ? $user : null;
        $can = fn (string $permission): bool => $user()?->hasPermission($permission) ?? false;
        // An office manages its own materials, Central the national ones.
        $ownScope = fn (DawahMaterial $record): bool => $record->tenant_id === app(TenantContext::class)->id();

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('language'))
            ->columns([
                TextColumn::make('title')->label(__('dawah.material_fields.title'))->searchable()->limit(40),
                TextColumn::make('material_type')->label(__('dawah.material_fields.material_type'))->formatStateUsing(fn (MaterialType $state): string => $state->label()),
                TextColumn::make('language.'.LocalizedName::column())->label(__('dawah.material_fields.language')),
                TextColumn::make('version')->label(__('dawah.material_fields.version')),
                TextColumn::make('scope')->label(__('dawah.material_fields.scope'))
                    ->state(fn (DawahMaterial $record): string => (string) __($record->isNational() ? 'dawah.material_fields.national' : 'dawah.material_fields.office')),
                TextColumn::make('stock_quantity')->label(__('dawah.material_fields.stock_quantity'))->placeholder('—')
                    ->visible(fn (): bool => app(TenantContext::class)->has()),
                TextColumn::make('status')->label(__('dawah.material_fields.status'))->badge()
                    ->formatStateUsing(fn (MaterialStatus $state): string => $state->label())->color(fn (MaterialStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('dawah.material_fields.status'))
                    ->options(collect(MaterialStatus::cases())->mapWithKeys(fn (MaterialStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('language_id')->label(__('dawah.material_fields.language'))
                    ->options(fn (): array => LocalizedName::options(Language::query())),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Model $record, array $data): Model => $record instanceof DawahMaterial ? self::save($record, $data) : $record),
                Action::make('attachFile')->label(__('dawah.material_actions.attach_file'))->icon('heroicon-o-paper-clip')
                    ->visible(fn (DawahMaterial $record): bool => $user()?->can('update', $record) ?? false)
                    ->schema([
                        FileUpload::make('file')->label(__('dawah.material_fields.file'))->required()->storeFiles(false)
                            ->maxSize((int) (config('documents.max_bytes') / 1024))
                            ->acceptedFileTypes(array_keys((array) config('documents.allowed_mime_types'))),
                    ])
                    ->action(self::domainAction(function (DawahMaterial $record, array $data, User $actor) use ($manage): void {
                        $file = $data['file'] ?? null;
                        $file instanceof TemporaryUploadedFile ? $manage()->attachFile($record, $file->getRealPath(), $file->getClientOriginalName(), $actor) : abort(422);
                    }, 'dawah.notifications.saved')),
                Action::make('download')->label(__('dawah.material_actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (DawahMaterial $record): bool => $record->file_document_id !== null)
                    ->action(function (DawahMaterial $record, $livewire) use ($manage, $user): void {
                        $livewire->redirect($manage()->downloadUrl($record, $user() ?? abort(403)));
                    }),
                Action::make('submit')->label(__('dawah.material_actions.submit'))->requiresConfirmation()
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::Draft && $ownScope($record) && $can('materials.create'))
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => $manage()->submitForReview($record, $actor), 'dawah.notifications.saved')),
                Action::make('approve')->label(__('dawah.material_actions.approve'))->color('success')->requiresConfirmation()
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::InReview && $ownScope($record) && $can('materials.approve') && $record->created_by !== $user()?->id)
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => $manage()->approve($record, $actor), 'dawah.notifications.saved')),
                Action::make('return')->label(__('dawah.material_actions.return'))->color('warning')
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::InReview && $ownScope($record) && $can('materials.approve'))
                    ->schema([Textarea::make('note')->label(__('dawah.material_fields.review_note'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => $manage()->returnToDraft($record, $actor, $data['note'] ?? null), 'dawah.notifications.saved')),
                Action::make('distribute')->label(__('dawah.material_actions.distribute'))->color('primary')
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::Approved && $can('materials.distribute'))
                    ->schema([
                        TextInput::make('quantity')->label(__('dawah.material_fields.quantity'))->integer()->minValue(1)->default(1)->required(),
                        Select::make('activity_plan_id')->label(__('dawah.material_fields.plan'))->searchable()
                            ->options(fn (): array => DawahActivityPlan::query()->latest('planned_date')->limit(200)->pluck('title', 'id')->all()),
                        TextInput::make('notes')->label(__('dawah.material_fields.notes'))->maxLength(1000),
                    ])
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => app(DistributeMaterial::class)->handle($record, [
                        'quantity' => (int) $data['quantity'],
                        'activity_plan_id' => blank($data['activity_plan_id'] ?? null) ? null : (int) $data['activity_plan_id'],
                        'notes' => blank($data['notes'] ?? null) ? null : (string) $data['notes'],
                    ], $actor), 'dawah.notifications.saved')),
                Action::make('addStock')->label(__('dawah.material_actions.add_stock'))->color('gray')
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::Approved && ! $record->isNational() && $ownScope($record) && $can('materials.create'))
                    ->schema([TextInput::make('quantity')->label(__('dawah.material_fields.quantity'))->integer()->minValue(1)->required()])
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => $manage()->addStock($record, (int) $data['quantity'], $actor), 'dawah.notifications.saved')),
                Action::make('newVersion')->label(__('dawah.material_actions.new_version'))->color('gray')
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::Approved && $ownScope($record) && $can('materials.create'))
                    ->schema([TextInput::make('version')->label(__('dawah.material_fields.version'))->required()->maxLength(20)])
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => $manage()->newVersion($record, (string) $data['version'], $actor), 'dawah.notifications.saved')),
                Action::make('retire')->label(__('dawah.material_actions.retire'))->color('danger')->requiresConfirmation()
                    ->visible(fn (DawahMaterial $record): bool => $record->status === MaterialStatus::Approved && $ownScope($record) && $can('materials.approve'))
                    ->action(self::domainAction(fn (DawahMaterial $record, array $data, User $actor) => $manage()->retire($record, $actor), 'dawah.notifications.saved')),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?DawahMaterial $material, array $data): DawahMaterial
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManageMaterials::class)->save($material, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }
}
