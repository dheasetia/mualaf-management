<?php

declare(strict_types=1);

namespace App\Filament\Center\Pages;

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Religion;
use App\Domain\Prospects\Services\ProspectConversion;
use App\Domain\Registration\Actions\RecordShahadah;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Filament\Center\Resources\Converts\ConvertResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Registration Officer form for RecordShahadah (shahadah.record). Opened from
 * a prospect page with ?prospect=<id>, or empty for a walk-in.
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
        return __('registration.page_title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'identity_type' => IdentityType::Iqamah->value,
            'shahadah_date' => CarbonImmutable::now((string) config('app.display_timezone'))->toDateString(),
            'prospect_id' => $this->prospect,
        ]);

        if ($this->prospect !== null) {
            $this->prefillFromProspect($this->prospect);
        }
    }

    public function form(Schema $schema): Schema
    {
        $options = static fn (array $cases): array => collect($cases)->mapWithKeys(fn ($case): array => [$case->value => $case->label()])->all();

        return $schema->statePath('data')->columns(2)->components([
            Section::make(__('registration.sections.source'))->columns(2)->columnSpanFull()->schema([
                Select::make('prospect_id')->label(__('registration.fields.prospect'))->searchable()->live()
                    ->helperText(__('registration.help.prospect'))
                    ->options(fn (): array => app(ProspectConversion::class)->readyOptions())
                    ->afterStateUpdated(fn (?int $state) => $state !== null ? $this->prefillFromProspect($state) : null),
                Select::make('primary_dai_id')->label(__('registration.fields.primary_dai'))->searchable()
                    ->options(fn (): array => app(DaiDirectory::class)->activeOptions())
                    ->required(fn (Get $get): bool => blank($get('prospect_id')))
                    ->hidden(fn (Get $get): bool => filled($get('prospect_id'))),
            ]),
            Section::make(__('registration.sections.identity'))->columns(3)->columnSpanFull()->schema([
                Select::make('identity_type')->label(__('converts.search.identity_type'))->required()->live()
                    ->options($options(IdentityType::cases())),
                Select::make('issuing_country_id')->label(__('converts.search.issuing_country'))->searchable()->required()
                    ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                    ->hidden(fn (Get $get): bool => IdentityType::tryFrom((string) $get('identity_type'))?->issuedBySaudiArabia() ?? false),
                TextInput::make('identity_number')->label(__('converts.search.identity_number'))->required()->maxLength(40)
                    ->autocomplete(false)->extraInputAttributes(['dir' => 'ltr']),
            ]),
            Section::make(__('registration.sections.profile'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('full_name')->label(__('converts.fields.full_name'))->required()->maxLength(255),
                TextInput::make('islamic_name')->label(__('converts.fields.islamic_name'))->maxLength(255),
                TextInput::make('name_ar')->label(__('converts.fields.name_ar'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                Select::make('gender')->label(__('converts.fields.gender'))->required()->options($options(Gender::cases())),
                DatePicker::make('date_of_birth')->label(__('converts.fields.date_of_birth'))->maxDate(now()),
                Select::make('nationality_id')->label(__('converts.fields.nationality'))->required()->searchable()
                    ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
                Select::make('native_language_id')->label(__('converts.fields.native_language'))->searchable()
                    ->options(fn (): array => Language::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
                Select::make('previous_religion_id')->label(__('converts.fields.previous_religion'))
                    ->options(fn (): array => Religion::query()->where('active', true)->orderBy('sort_order')->pluck('name_ar', 'id')->all()),
            ]),
            Section::make(__('registration.sections.shahadah'))->columns(2)->columnSpanFull()->schema([
                DatePicker::make('shahadah_date')->label(__('registration.fields.shahadah_date'))->required()->maxDate(now()),
                TimePicker::make('shahadah_time')->label(__('registration.fields.shahadah_time'))->seconds(false),
                TextInput::make('location')->label(__('registration.fields.location'))->required()->maxLength(255),
                Select::make('city_id')->label(__('registration.fields.city'))->searchable()
                    ->options(fn (): array => City::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all()),
                Select::make('preacher_dai_id')->label(__('registration.fields.preacher'))->searchable()
                    ->options(fn (): array => app(DaiDirectory::class)->activeOptions()),
                Textarea::make('notes')->label(__('registration.fields.notes'))->maxLength(2000)->columnSpanFull(),
            ]),
            Section::make(__('registration.sections.contact'))->columns(2)->columnSpanFull()
                ->hidden(fn (Get $get): bool => filled($get('prospect_id')))
                ->schema([
                    TextInput::make('phone')->label(__('prospects.fields.phone'))->tel()->maxLength(30)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('email')->label(__('prospects.fields.email'))->email()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
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
                    Actions::make([Action::make('register')->label(__('registration.actions.register'))->submit('register')]),
                ]),
        ]);
    }

    public function register(): void
    {
        $state = $this->form->getState();
        $id = static fn (mixed $value): ?int => blank($value) ? null : (int) $value;
        $text = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);

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
}
