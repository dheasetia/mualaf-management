<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Services\NationalSearch;
use App\Domain\Platform\Models\Country;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Exact identity search across centers before registering a convert
 * (identity.national_search). Shows only what NationalSearchResult allows.
 *
 * @property-read Schema $form
 */
final class NationalIdentitySearch extends Page implements HasForms
{
    use InteractsWithForms;
    use ResolvesActor;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 20;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array{found: bool, inOwnOffice: bool, officeCode: ?string, officeName: ?string, displayName: ?string, withheld: bool}|null */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('identity.national_search') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('converts.search.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public function getTitle(): string
    {
        return __('converts.search.title');
    }

    public function mount(): void
    {
        $this->form->fill(['identity_type' => IdentityType::Iqamah->value]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('identity_type')->label(__('converts.search.identity_type'))->required()->live()
                    ->options(collect(IdentityType::cases())->mapWithKeys(fn (IdentityType $type): array => [$type->value => $type->label()])->all()),
                Select::make('issuing_country_id')->label(__('converts.search.issuing_country'))->searchable()
                    ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                    ->hidden(fn (Get $get): bool => IdentityType::tryFrom((string) $get('identity_type'))?->issuedBySaudiArabia() ?? false)
                    ->required(),
                TextInput::make('identity_number')->label(__('converts.search.identity_number'))->required()->maxLength(40)
                    ->autocomplete(false)->extraInputAttributes(['dir' => 'ltr']),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(__('converts.search.description', ['max' => NationalSearch::MAX_PER_HOUR]))->info(),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('search')
                ->footer([
                    Actions::make([Action::make('search')->label(__('converts.search.submit'))->submit('search')]),
                ]),
            Callout::make(fn (): string => $this->resultMessage())
                ->color(fn (): string => ($this->result['found'] ?? false) ? 'warning' : 'success')
                ->visible(fn (): bool => $this->result !== null),
        ]);
    }

    public function search(): void
    {
        $state = $this->form->getState();
        $this->result = null;

        try {
            $result = app(NationalSearch::class)->search($this->actor(), new IdentifierData(
                IdentityType::from((string) $state['identity_type']),
                (string) $state['identity_number'],
                blank($state['issuing_country_id'] ?? null) ? null : (int) $state['issuing_country_id'],
            ));
            $this->result = $result->toArray();
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }

        // The number is not kept on the page after the search.
        $this->data['identity_number'] = null;
    }

    private function resultMessage(): string
    {
        $result = $this->result;

        if ($result === null || ! $result['found']) {
            return __('converts.search.not_found');
        }

        if ($result['withheld']) {
            return __('converts.search.withheld');
        }

        $where = $result['inOwnOffice']
            ? __('converts.search.found_here')
            : __('converts.search.found_elsewhere', ['office' => "{$result['officeName']} ({$result['officeCode']})"]);

        return $where.' '.__('converts.search.name', ['name' => (string) $result['displayName']]);
    }
}
