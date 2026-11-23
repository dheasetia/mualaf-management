<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Dais\Schemas;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Dai\Enums\LanguageProficiency;
use App\Domain\Dai\Models\Dai;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\Language;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * State only: CreateDai / EditDai pass it to the domain actions, which own the
 * rules (supervisor chain, membership, uniqueness). Rules repeated here are for
 * early feedback.
 */
final class DaiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make(__('dai.sections.profile'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('employee_number')->label(__('dai.fields.employee_number'))
                    ->required()->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule
                        ->where('tenant_id', app(TenantContext::class)->requireId())),
                TextInput::make('full_name')->label(__('dai.fields.full_name'))->required()->maxLength(255),
                TextInput::make('full_name_ar')->label(__('dai.fields.full_name_ar'))->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),
                Select::make('gender')->label(__('dai.fields.gender'))->required()
                    ->options(self::options(Gender::cases())),
                Select::make('nationality_id')->label(__('dai.fields.nationality'))
                    ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                    ->searchable(),
                TextInput::make('education')->label(__('dai.fields.education'))->maxLength(255),
                TagsInput::make('specialties')->label(__('dai.fields.specialties'))->columnSpanFull(),
            ]),
            Section::make(__('dai.sections.contact'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('phone')->label(__('dai.fields.phone'))->tel()->maxLength(30)
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('email')->label(__('dai.fields.email'))->email()->maxLength(255)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Select::make('user_id')->label(__('dai.fields.user'))
                    ->helperText(__('dai.help.user'))
                    ->options(fn (): array => self::memberOptions())
                    ->searchable(),
            ]),
            Section::make(__('dai.sections.employment'))->columns(2)->columnSpanFull()->schema([
                Select::make('employment_status')->label(__('dai.fields.employment_status'))->required()
                    ->options(self::options(EmploymentStatus::cases())),
                DatePicker::make('joined_at')->label(__('dai.fields.joined_at')),
                Select::make('supervisor_dai_id')->label(__('dai.fields.supervisor'))
                    ->options(fn (?Dai $record): array => Dai::query()
                        ->where('status', DaiStatus::Active)
                        ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record?->id))
                        ->orderBy('full_name')
                        ->pluck('full_name', 'id')
                        ->all())
                    ->searchable(),
            ]),
            Section::make(__('dai.sections.languages'))->columnSpanFull()->schema([
                Repeater::make('language_rows')->hiddenLabel()
                    ->schema([
                        Select::make('language_id')->label(__('dai.fields.language'))->required()
                            ->options(fn (): array => Language::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                            ->searchable()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Select::make('proficiency')->label(__('dai.fields.proficiency'))->required()
                            ->options(self::options(LanguageProficiency::cases())),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel(__('dai.actions.add_language')),
            ]),
        ]);
    }

    /**
     * Users with an active membership in this office.
     *
     * @return array<int, string>
     */
    private static function memberOptions(): array
    {
        return User::query()
            ->whereHas('tenantMemberships', fn (Builder $query) => $query
                ->where('tenant_id', app(TenantContext::class)->requireId())
                ->where('status', MembershipStatus::Active))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"])
            ->all();
    }

    /**
     * @param  list<Gender|EmploymentStatus|LanguageProficiency>  $cases
     * @return array<string, string>
     */
    private static function options(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
