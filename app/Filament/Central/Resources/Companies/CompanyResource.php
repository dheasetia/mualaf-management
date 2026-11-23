<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Companies;

use App\Domain\Companies\Actions\PromoteCompany;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Scopes\CompanyScope;
use App\Filament\Central\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Schemas\CompanySchema;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The national company directory, and office companies to promote into it.
 */
final class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getRecordTitle(?Model $record): string
    {
        return LocalizedName::of($record) ?? self::getModelLabel();
    }

    protected static ?int $navigationSort = 80;

    public static function getModelLabel(): string
    {
        return __('companies.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('companies.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(CompanyScope::class)->with(['city', 'tenant']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(CompanySchema::form());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name_ar')
            ->columns(CompanySchema::columns())
            ->filters([
                TernaryFilter::make('national')->label(__('companies.fields.owner'))
                    ->trueLabel(__('companies.owner.national'))->falseLabel(__('companies.owner.office'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('tenant_id'),
                        false: fn (Builder $query) => $query->whereNotNull('tenant_id'),
                    ),
            ])
            ->recordActions([
                CompanySchema::mapAction(),
                EditAction::make()->using(fn (Model $record, array $data): Model => $record instanceof Company ? CompanySchema::save($record, $data) : $record),
                Action::make('promote')->label(__('companies.actions.promote'))->icon('heroicon-o-globe-alt')
                    ->requiresConfirmation()
                    ->authorize(fn (Company $record): bool => auth()->user()?->can('promote', $record) ?? false)
                    ->action(function (Company $record): void {
                        $user = auth()->user();

                        if ($user instanceof User) {
                            app(PromoteCompany::class)->handle($record, $user);
                            Notification::make()->success()->title(__('companies.notifications.promoted'))->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanies::route('/'),
        ];
    }
}
