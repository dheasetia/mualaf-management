<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Religion;
use App\Domain\Prospects\Services\ProspectConversion;
use App\Domain\Registration\Actions\CorrectShahadahRegistration;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Data\ShahadahCorrectionData;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Data\ShahadahWitnessData;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahWitness;
use App\Filament\Center\Resources\ConvertRegistrations\ConvertRegistrationResource;
use App\Filament\Center\Resources\Converts\ConvertResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Registration Officer form for RecordShahadah (shahadah.record). Opened from
 * a prospect page with ?prospect=<id>, or empty for a walk-in. With
 * ?registration=<id> it corrects a registration returned by the verifier
 * (CorrectShahadahRegistration): no source or contact, the identity number
 * only when it was wrong.
 *
 * @property-read Schema $form
 */
final class RegisterShahadah extends Page implements HasForms
{
    use InteractsWithForms;
    use ResolvesActor;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 21;

    #[Url]
    public ?int $prospect = null;

    #[Url]
    public ?int $registration = null;

    /** Last four digits of the current number, when correcting. */
    public ?string $currentIdentityLast4 = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create', ConvertRegistration::class) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('registration.page_title');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public function getTitle(): string
    {
        $record = $this->correcting();

        return $record === null ? __('registration.page_title') : __('registration.page_title_correct', ['number' => $record->registration_no]);
    }

    public function mount(): void
    {
        if ($this->registration !== null) {
            $this->prefillFromRegistration($this->correctingOrFail());

            return;
        }

        $this->form->fill([
            'identity_type' => IdentityType::Iqamah->value,
            'shahadah_date' => CarbonImmutable::now((string) config('app.display_timezone'))->toDateString(),
            'prospect_id' => $this->prospect,
            'witnesses' => array_fill(0, (int) config('registration.shahadah_min_witnesses'), []),
        ]);

        if ($this->prospect !== null) {
            $this->prefillFromProspect($this->prospect);
        }
    }

    public function form(Schema $schema): Schema
    {
        $options = static fn (array $cases): array => collect($cases)->mapWithKeys(fn ($case): array => [$case->value => $case->label()])->all();

        return $schema->statePath('data')->components([
            Section::make(__('registration.page_title'))->columnSpanFull()->schema([
                Fieldset::make(__('registration.sections.source'))->columns(2)->hidden(fn (): bool => $this->registration !== null)->schema([
                    Select::make('prospect_id')->label(__('registration.fields.prospect'))->searchable()->live()
                        ->helperText(__('registration.help.prospect'))
                        ->options(fn (): array => app(ProspectConversion::class)->readyOptions())
                        ->afterStateUpdated(fn (?int $state) => $state !== null ? $this->prefillFromProspect($state) : null),
                    Select::make('primary_dai_id')->label(__('registration.fields.primary_dai'))->searchable()
                        ->options(fn (): array => app(DaiDirectory::class)->activeOptions())
                        ->required(fn (Get $get): bool => blank($get('prospect_id')))
                        ->hidden(fn (Get $get): bool => filled($get('prospect_id'))),
                ]),
                Fieldset::make(__('registration.sections.identity'))->columns(3)->schema([
                    Toggle::make('replace_identity')->label(__('registration.fields.replace_identity'))->live()->columnSpanFull()
                        ->helperText(fn (): string => __('registration.help.replace_identity', ['last4' => (string) $this->currentIdentityLast4]))
                        ->visible(fn (): bool => $this->registration !== null),
                    Select::make('identity_type')->label(__('converts.search.identity_type'))->required()->live()
                        ->options($options(IdentityType::cases()))
                        ->visible(fn (Get $get): bool => $this->entersIdentity($get)),
                    Select::make('issuing_country_id')->label(__('converts.search.issuing_country'))->searchable()->required()
                        ->options(fn (): array => LocalizedName::options(Country::query()))
                        ->hidden(fn (Get $get): bool => ! $this->entersIdentity($get) || (IdentityType::tryFrom((string) $get('identity_type'))?->issuedBySaudiArabia() ?? false)),
                    TextInput::make('identity_number')->label(__('converts.search.identity_number'))->required()->maxLength(40)
                        ->autocomplete(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible(fn (Get $get): bool => $this->entersIdentity($get)),
                ]),
                Fieldset::make(__('registration.sections.profile'))->columns(2)->schema([
                    TextInput::make('full_name')->label(__('converts.fields.full_name'))->required()->maxLength(255),
                    TextInput::make('islamic_name')->label(__('converts.fields.islamic_name'))->maxLength(255),
                    TextInput::make('name_ar')->label(__('converts.fields.name_ar'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                    Select::make('gender')->label(__('converts.fields.gender'))->required()->options($options(Gender::cases())),
                    DatePicker::make('date_of_birth')->label(__('converts.fields.date_of_birth'))->maxDate(fn (): string => CarbonImmutable::now((string) config('app.display_timezone'))->toDateString()),
                    Select::make('nationality_id')->label(__('converts.fields.nationality'))->required()->searchable()
                        ->options(fn (): array => LocalizedName::options(Country::query())),
                    Select::make('native_language_id')->label(__('converts.fields.native_language'))->searchable()
                        ->options(fn (): array => LocalizedName::options(Language::query())),
                    Select::make('previous_religion_id')->label(__('converts.fields.previous_religion'))
                        ->hidden(fn (): bool => $this->registration !== null && ! (auth()->user()?->hasPermission('converts.view_sensitive') ?? false))
                        ->options(fn (): array => LocalizedName::options(Religion::query()->where('active', true)->orderBy('sort_order'), sortByName: false)),
                ]),
                Fieldset::make(__('registration.sections.shahadah'))->columns(2)->schema([
                    DatePicker::make('shahadah_date')->label(__('registration.fields.shahadah_date'))->required()->maxDate(fn (): string => CarbonImmutable::now((string) config('app.display_timezone'))->toDateString()),
                    TimePicker::make('shahadah_time')->label(__('registration.fields.shahadah_time'))->seconds(false),
                    TextInput::make('location')->label(__('registration.fields.location'))->required()->maxLength(255),
                    Select::make('city_id')->label(__('registration.fields.city'))->searchable()
                        ->options(fn (): array => LocalizedName::options(City::query())),
                    Select::make('preacher_dai_id')->label(__('registration.fields.preacher'))->searchable()
                        ->options(fn (): array => app(DaiDirectory::class)->activeOptions()),
                    Textarea::make('notes')->label(__('registration.fields.notes'))->maxLength(2000)->columnSpanFull(),
                ]),
                Fieldset::make(__('registration.sections.witnesses'))->columns(1)
                    ->schema([
                        Text::make(__('registration.help.witnesses'))->color('gray'),
                        Repeater::make('witnesses')->hiddenLabel()
                            ->schema([
                                TextInput::make('name')->label(__('registration.fields.witness_name'))->required()->maxLength(255),
                                TextInput::make('phone')->label(__('registration.fields.witness_phone'))->tel()->required()->maxLength(30)
                                    ->extraInputAttributes(['dir' => 'ltr']),
                                TextInput::make('identity_last4')->label(__('registration.fields.witness_identity_last4'))->length(4)
                                    ->autocomplete(false)->extraInputAttributes(['dir' => 'ltr']),
                                TextInput::make('relationship')->label(__('registration.fields.witness_relationship'))->maxLength(100),
                            ])
                            ->columns(4)
                            ->defaultItems((int) config('registration.shahadah_min_witnesses'))
                            ->minItems((int) config('registration.shahadah_min_witnesses'))
                            ->maxItems((int) config('registration.shahadah_max_witnesses'))
                            ->addActionLabel(__('registration.actions.add_witness')),
                    ]),
                Fieldset::make(__('registration.sections.contact'))->columns(2)
                    ->hidden(fn (Get $get): bool => filled($get('prospect_id')) || $this->registration !== null)
                    ->schema([
                        TextInput::make('phone')->label(__('prospects.fields.phone'))->tel()->maxLength(30)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('email')->label(__('prospects.fields.email'))->email()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('register')
                ->footer([
                    Actions::make([Action::make('register')->submit('register')
                        ->label(fn (): string => $this->registration === null ? __('registration.actions.register') : __('registration.actions.resubmit'))]),
                ]),
        ]);
    }

    public function register(): void
    {
        $state = $this->form->getState();
        $id = static fn (mixed $value): ?int => blank($value) ? null : (int) $value;
        $text = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);

        if ($this->registration !== null) {
            $this->correct($state, $id, $text);

            return;
        }

        try {
            $registration = app(RecordShahadah::class)->handle(new ShahadahRegistrationData(
                profile: ConvertProfileData::fromForm($state),
                identity: new IdentifierData(
                    IdentityType::from((string) $state['identity_type']),
                    (string) $state['identity_number'],
                    $id($state['issuing_country_id'] ?? null),
                ),
                shahadahDate: CarbonImmutable::parse((string) $state['shahadah_date']),
                location: trim((string) $state['location']),
                prospectId: $id($state['prospect_id'] ?? null),
                primaryDaiId: $id($state['primary_dai_id'] ?? null),
                shahadahTime: $text($state['shahadah_time'] ?? null),
                cityId: $id($state['city_id'] ?? null),
                preacherDaiId: $id($state['preacher_dai_id'] ?? null),
                notes: $text($state['notes'] ?? null),
                phone: $text($state['phone'] ?? null),
                email: $text($state['email'] ?? null),
                witnesses: array_values(array_map(ShahadahWitnessData::fromForm(...), $state['witnesses'] ?? [])),
            ), $this->actor());
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }

        Notification::make()->success()
            ->title(__('registration.notifications.registered', ['number' => $registration->registration_no]))
            ->send();

        $this->redirect(ConvertResource::getUrl('view', ['record' => $registration->convert_id]));
    }

    private function prefillFromProspect(int $prospectId): void
    {
        try {
            $snapshot = app(ProspectConversion::class)->snapshot($prospectId);
        } catch (RuleViolation) {
            $this->data['prospect_id'] = null;

            return;
        }

        $this->data = [
            ...$this->data,
            'prospect_id' => $snapshot->id,
            'full_name' => $snapshot->fullName,
            'gender' => $snapshot->gender->value,
            'nationality_id' => $snapshot->nationalityId,
            'native_language_id' => $snapshot->nativeLanguageId,
            'previous_religion_id' => $snapshot->currentReligionId,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  Closure(mixed): ?int  $id
     * @param  Closure(mixed): ?string  $text
     */
    private function correct(array $state, Closure $id, Closure $text): void
    {
        $registration = $this->correctingOrFail();
        $convert = Convert::query()->findOrFail($registration->convert_id);

        // Fields this form does not show keep their current values.
        $profile = ConvertProfileData::fromForm([
            'previous_name' => $convert->previous_name,
            'previous_religion_id' => $convert->previous_religion_id,
            'previous_religion_other' => $convert->previous_religion_other,
            ...$state,
        ]);

        try {
            app(CorrectShahadahRegistration::class)->handle($registration, new ShahadahCorrectionData(
                profile: $profile,
                shahadahDate: CarbonImmutable::parse((string) $state['shahadah_date']),
                location: trim((string) $state['location']),
                witnesses: array_values(array_map(ShahadahWitnessData::fromForm(...), $state['witnesses'] ?? [])),
                identity: (bool) ($state['replace_identity'] ?? false) ? new IdentifierData(
                    IdentityType::from((string) $state['identity_type']),
                    (string) $state['identity_number'],
                    $id($state['issuing_country_id'] ?? null),
                ) : null,
                shahadahTime: $text($state['shahadah_time'] ?? null),
                cityId: $id($state['city_id'] ?? null),
                preacherDaiId: $id($state['preacher_dai_id'] ?? null),
                notes: $text($state['notes'] ?? null),
            ), $this->actor());
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }

        Notification::make()->success()->title(__('registration.notifications.corrected'))->send();

        $this->redirect(ConvertRegistrationResource::getUrl());
    }

    private function entersIdentity(Get $get): bool
    {
        return $this->registration === null || (bool) $get('replace_identity');
    }

    private function correcting(): ?ConvertRegistration
    {
        return $this->registration === null ? null : ConvertRegistration::query()->find($this->registration);
    }

    private function correctingOrFail(): ConvertRegistration
    {
        $registration = $this->correcting();

        abort_unless($registration !== null && (auth()->user()?->can('correct', $registration) ?? false), 403);

        return $registration;
    }

    private function prefillFromRegistration(ConvertRegistration $registration): void
    {
        $convert = Convert::query()->findOrFail($registration->convert_id);
        $shahadah = ShahadahRecord::query()->where('convert_registration_id', $registration->id)->firstOrFail();
        $this->currentIdentityLast4 = ConvertIdentifier::query()->where('convert_id', $convert->id)->where('is_primary', true)->whereNull('replaced_at')->value('identity_last4');
        $sensitive = auth()->user()?->hasPermission('converts.view_sensitive') ?? false;

        $this->form->fill([
            'replace_identity' => false,
            'identity_type' => IdentityType::Iqamah->value,
            'full_name' => $convert->full_name,
            'islamic_name' => $convert->islamic_name,
            'name_ar' => $convert->name_ar,
            'gender' => $convert->gender->value,
            'date_of_birth' => $convert->date_of_birth?->toDateString(),
            'nationality_id' => $convert->nationality_id,
            'native_language_id' => $convert->native_language_id,
            'previous_religion_id' => $sensitive ? $convert->previous_religion_id : null,
            'shahadah_date' => $shahadah->shahadah_date->toDateString(),
            'shahadah_time' => $shahadah->shahadah_time,
            'location' => $shahadah->location,
            'city_id' => $shahadah->city_id,
            'preacher_dai_id' => $shahadah->preacher_dai_id,
            'notes' => $shahadah->notes,
            'witnesses' => ShahadahWitness::query()->where('shahadah_record_id', $shahadah->id)->orderBy('id')->get()
                ->map(fn (ShahadahWitness $witness): array => [
                    'name' => $witness->name,
                    'phone' => $witness->phone_encrypted,
                    'identity_last4' => $witness->identity_last4,
                    'relationship' => $witness->relationship,
                ])->all(),
        ]);
    }
}
