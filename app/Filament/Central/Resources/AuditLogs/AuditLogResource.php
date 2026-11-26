<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\AuditLogs;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Models\AuditLog;
use App\Filament\Central\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only national audit trail (audit.view_national). The Center panel
 * resource extends this one and narrows it to the office in context.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'action';

    protected static ?int $navigationSort = 90;

    /** Whether the tenant column and filter are shown (national view only). */
    protected static bool $showsTenant = true;

    public static function getModelLabel(): string
    {
        return __('audit.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('audit.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['actor', 'tenant']);
    }

    public static function infolist(Schema $schema): Schema
    {
        $json = static fn (mixed $state): ?string => $state === null
            ? null
            : (json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);

        return $schema
            ->columns(2)
            ->components([
                Section::make(__('audit.model'))->columnSpanFull()->schema([
                    Fieldset::make(__('forms.record'))->columns(2)->schema([
                        TextEntry::make('created_at')->label(__('audit.fields.created_at'))
                            ->dateTime('Y-m-d H:i:s', timezone: self::displayTimezone()),
                        TextEntry::make('action')->label(__('audit.fields.action'))->badge(),
                        TextEntry::make('actor.name')->label(__('audit.fields.actor'))
                            ->placeholder(fn (AuditLog $record): string => __('audit.actor_types.'.$record->actor_type->value)),
                        TextEntry::make('tenant.'.LocalizedName::column())->label(__('audit.fields.tenant'))
                            ->placeholder(__('audit.platform'))
                            ->visible(static::$showsTenant),
                        TextEntry::make('subject_type')->label(__('audit.fields.subject'))
                            ->formatStateUsing(fn (AuditLog $record): string => $record->subject_type.' #'.$record->subject_id)
                            ->placeholder('—'),
                        TextEntry::make('privileged_grant_id')->label(__('audit.fields.privileged_grant'))->placeholder('—'),
                    ]),
                    Fieldset::make(__('forms.audit'))->columns(2)->schema([
                        TextEntry::make('ip_address')->label(__('audit.fields.ip_address'))->placeholder('—'),
                        TextEntry::make('request_id')->label(__('audit.fields.request_id'))->copyable(),
                        TextEntry::make('user_agent')->label(__('audit.fields.user_agent'))->placeholder('—')->columnSpanFull(),
                    ]),
                    Fieldset::make(__('forms.details'))->columns(1)->schema([
                        TextEntry::make('old_values_redacted')->label(__('audit.fields.old_values'))
                            ->formatStateUsing($json)->fontFamily('mono')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('new_values_redacted')->label(__('audit.fields.new_values'))
                            ->formatStateUsing($json)->fontFamily('mono')->placeholder('—')->columnSpanFull(),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // No COUNT(*) over a table expected to reach tens of millions of rows.
            ->paginationMode(PaginationMode::Simple)
            ->columns([
                TextColumn::make('created_at')->label(__('audit.fields.created_at'))
                    ->dateTime('Y-m-d H:i:s', timezone: self::displayTimezone())
                    ->sortable(),
                TextColumn::make('action')->label(__('audit.fields.action'))->badge(),
                TextColumn::make('actor.name')->label(__('audit.fields.actor'))
                    ->placeholder(fn (AuditLog $record): string => __('audit.actor_types.'.$record->actor_type->value)),
                TextColumn::make('tenant.'.LocalizedName::column())->label(__('audit.fields.tenant'))
                    ->placeholder(__('audit.platform'))
                    ->visible(static::$showsTenant),
                TextColumn::make('subject_type')->label(__('audit.fields.subject'))
                    ->formatStateUsing(fn (AuditLog $record): string => $record->subject_type.' #'.$record->subject_id)
                    ->placeholder('—'),
                TextColumn::make('ip_address')->label(__('audit.fields.ip_address'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_id')->label(__('audit.fields.request_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('action')
                    ->schema([
                        TextInput::make('action')->label(__('audit.fields.action'))
                            ->placeholder('roles.'),
                    ])
                    // Prefix match keeps the (action, created_at) index usable.
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['action'] ?? null),
                        fn (Builder $query): Builder => $query->where('action', 'like', addcslashes((string) $data['action'], '%_\\').'%'),
                    )),
                SelectFilter::make('actor_type')->label(__('audit.fields.actor_type'))
                    ->options(collect(ActorType::cases())->mapWithKeys(
                        fn (ActorType $type): array => [$type->value => __('audit.actor_types.'.$type->value)],
                    )->all()),
                SelectFilter::make('tenant')->label(__('audit.fields.tenant'))
                    ->relationship('tenant', LocalizedName::column())
                    ->searchable()
                    ->preload()
                    ->visible(static::$showsTenant),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('audit.filters.from')),
                        DatePicker::make('until')->label(__('audit.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', self::startOfDisplayDay($date)))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<', self::startOfDisplayDay($date)->addDay()))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }

    private static function displayTimezone(): string
    {
        return (string) config('app.display_timezone');
    }

    /** Dates are picked in display time; audit_logs.created_at is UTC. */
    private static function startOfDisplayDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::displayTimezone())->startOfDay()->utc();
    }
}
