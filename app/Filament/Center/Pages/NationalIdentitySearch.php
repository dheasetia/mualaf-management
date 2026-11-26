<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Services\NationalSearch;
use App\Domain\Platform\Models\Country;
use App\Domain\Transfers\Actions\RequestConvertTransfer;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
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

    /** The last searched identity, encrypted, so a transfer can be requested without showing the number again. */
    public ?string $lastIdentity = null;

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
                Section::make(__('converts.search.title'))->columnSpanFull()->schema([
                    Fieldset::make(__('forms.identity'))->columns(3)->schema([
                        Select::make('identity_type')->label(__('converts.search.identity_type'))->required()->live()
                            ->options(collect(IdentityType::cases())->mapWithKeys(fn (IdentityType $type): array => [$type->value => $type->label()])->all()),
                        Select::make('issuing_country_id')->label(__('converts.search.issuing_country'))->searchable()
                            ->options(fn (): array => LocalizedName::options(Country::query()))
                            ->hidden(fn (Get $get): bool => IdentityType::tryFrom((string) $get('identity_type'))?->issuedBySaudiArabia() ?? false)
                            ->required(),
                        TextInput::make('identity_number')->label(__('converts.search.identity_number'))->required()->maxLength(40)
                            ->autocomplete(false)->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                ]),
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
                ->visible(fn (): bool => $this->result !== null)
                ->actions([
                    Action::make('requestTransfer')->label(__('transfers.actions.request'))
                        ->visible(fn (): bool => $this->result !== null && $this->result['found'] && ! $this->result['inOwnOffice']
                            && (auth()->user()?->hasPermission('converts.transfer') ?? false))
                        ->schema([Textarea::make('reason')->label(__('transfers.fields.reason'))->required()->maxLength(1000)])
                        ->action(fn (array $data) => $this->requestTransfer((string) $data['reason'])),
                ]),
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
            $this->lastIdentity = Crypt::encrypt([
                'type' => (string) $state['identity_type'],
                'number' => (string) $state['identity_number'],
                'country' => blank($state['issuing_country_id'] ?? null) ? null : (int) $state['issuing_country_id'],
            ]);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }

        // The number is not kept on the page after the search.
        $this->data['identity_number'] = null;
    }

    public function requestTransfer(string $reason): void
    {
        if ($this->lastIdentity === null) {
            return;
        }

        /** @var array{type: string, number: string, country: int|null} $identity */
        $identity = Crypt::decrypt($this->lastIdentity);

        try {
            app(RequestConvertTransfer::class)->handle(
                new IdentifierData(IdentityType::from($identity['type']), $identity['number'], $identity['country']),
                $reason,
                $this->actor(),
            );
        } catch (RuleViolation $violation) {
            Notification::make()->danger()->title($violation->getMessage())->send();

            return;
        }

        $this->lastIdentity = null;
        Notification::make()->success()->title(__('transfers.notifications.requested'))->send();
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
