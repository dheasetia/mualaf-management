<?php

declare(strict_types=1);

namespace App\Filament\Central\MasterData;

use App\Support\Localization\LocalizedName;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Shared shape of the Central master-data screens: a code, Arabic and English
 * names, per-resource extra fields, and soft delete where the table has it.
 * Authorization comes from MasterDataPolicy.
 */
abstract class MasterDataResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getRecordTitle(?Model $record): string
    {
        return LocalizedName::of($record) ?? static::getModelLabel();
    }

    /** Key under lang/{locale}/platform.php, such as "regions". */
    abstract protected static function translationKey(): string;

    public static function getModelLabel(): string
    {
        return __('platform.'.static::translationKey().'.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('platform.'.static::translationKey().'.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('platform.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    ...static::codeComponents(),
                    TextInput::make('name_ar')->label(__('platform.fields.name_ar'))
                        ->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                    TextInput::make('name_en')->label(__('platform.fields.name_en'))
                        ->required()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    ...static::extraFormComponents(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...static::codeColumns(),
                TextColumn::make('name_ar')->label(__('platform.fields.name_ar'))->searchable()->sortable(),
                TextColumn::make('name_en')->label(__('platform.fields.name_en'))->searchable()->sortable(),
                ...static::extraColumns(),
            ])
            ->filters([
                ...static::extraFilters(),
                ...(static::usesSoftDeletes() ? [TrashedFilter::make()] : []),
            ])
            ->recordActions([
                EditAction::make(),
                ...(static::usesSoftDeletes() ? [DeleteAction::make(), RestoreAction::make()] : []),
            ])
            ->defaultSort(static::defaultSortColumn());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $query = parent::getRecordRouteBindingEloquentQuery();

        return static::usesSoftDeletes() ? $query->withoutGlobalScopes([SoftDeletingScope::class]) : $query;
    }

    protected static function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::getModel()), true);
    }

    protected static function defaultSortColumn(): string
    {
        return 'code';
    }

    /** @return list<Component> */
    protected static function codeComponents(): array
    {
        return [
            static::codeInput('code', static::codePattern(), static::codeMaxLength(), static::codeIsUppercase()),
        ];
    }

    /** @return list<Column> */
    protected static function codeColumns(): array
    {
        return [
            TextColumn::make('code')->label(__('platform.fields.code'))->searchable()->sortable()->fontFamily('mono'),
        ];
    }

    /** Letters and digits by default (matched case-insensitively); snake_case resources override it. */
    protected static function codePattern(): string
    {
        return '/^[a-z0-9]+$/i';
    }

    /** Stored case of the code: uppercase (regions, cities) or lowercase (snake_case codes). */
    protected static function codeIsUppercase(): bool
    {
        return true;
    }

    protected static function codeMaxLength(): int
    {
        return 10;
    }

    /**
     * A code input stored in one case and unique across every row, soft-deleted
     * ones included (they still hold the unique key). The ai_ci collation makes
     * the unique check case-insensitive, so "dmm" collides with "DMM".
     */
    protected static function codeInput(string $column, string $pattern, int $maxLength, bool $uppercase): TextInput
    {
        return TextInput::make($column)
            ->label(__('platform.fields.'.$column))
            ->required()
            ->maxLength($maxLength)
            ->regex($pattern)
            ->unique(ignoreRecord: true)
            ->dehydrateStateUsing(fn (?string $state): ?string => self::normaliseCode($state, $uppercase))
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /** @return list<Component> */
    protected static function extraFormComponents(): array
    {
        return [];
    }

    /** @return list<Column> */
    protected static function extraColumns(): array
    {
        return [];
    }

    /** @return list<BaseFilter> */
    protected static function extraFilters(): array
    {
        return [];
    }

    private static function normaliseCode(?string $state, bool $uppercase): ?string
    {
        if ($state === null) {
            return null;
        }

        $state = trim($state);

        return $uppercase ? strtoupper($state) : strtolower($state);
    }
}
