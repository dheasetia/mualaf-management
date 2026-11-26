<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Referrals;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\International\Actions\ReferralWorkflow;
use App\Domain\International\Enums\OrganizationStatus;
use App\Domain\International\Enums\ReferralStatus;
use App\Domain\International\Enums\SharedField;
use App\Domain\International\Models\ExternalIslamicOrganization;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\International\Services\ReferralPacket;
use App\Domain\Platform\Models\Country;
use App\Filament\Center\Resources\Referrals\Pages\ManageReferrals;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * International follow-up referrals of the office (10 §10.7).
 */
final class ReferralResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = InternationalReferral::class;

    protected static ?string $slug = 'referrals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('international.referrals.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('international.referrals.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('international.navigation_group');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->hasPermission('international.referrals.create') || $user->hasPermission('international.referrals.send'));
    }

    public static function table(Table $table): Table
    {
        $workflow = fn (): ReferralWorkflow => app(ReferralWorkflow::class);
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;
        $at = fn (ReferralStatus ...$statuses): Closure => fn (InternationalReferral $record): bool => in_array($record->status, $statuses, true);
        $reason = Textarea::make('reason')->label(__('international.fields.reason'))->required()->maxLength(1000);
        $step = fn (string $name, Closure $visible, Closure $handler): Action => Action::make($name)->label(__('international.actions.'.$name))->visible($visible)
            ->action(self::domainAction($handler, 'international.notifications.saved'));

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('convert')->label(__('international.fields.convert'))
                    ->state(fn (InternationalReferral $record): string => ($user = auth()->user()) instanceof User && ($convert = Convert::query()->find($record->convert_id)) instanceof Convert
                        ? app(ConvertAccess::class)->displayName($user, $convert) : '—'),
                TextColumn::make('destination')->label(__('international.fields.destination_country'))
                    ->state(fn (InternationalReferral $record): string => trim(LocalizedName::of(Country::query()->find($record->destination_country_id)).' '.$record->destination_city)),
                TextColumn::make('status')->label(__('international.fields.status'))->badge()
                    ->formatStateUsing(fn (ReferralStatus $state): string => $state->label())->color(fn (ReferralStatus $state): string => $state->color()),
                TextColumn::make('sent_at')->label(__('international.fields.sent_at'))->date('Y-m-d', (string) config('app.display_timezone'))->placeholder('—'),
            ])
            ->headerActions([
                Action::make('createReferral')->label(__('international.actions.create_referral'))->visible(fn (): bool => $can('international.referrals.create'))
                    ->schema([
                        Section::make()->columnSpanFull()->schema([
                            Fieldset::make(__('forms.basic'))->columns(2)->schema([
                                Select::make('convert_id')->label(__('international.fields.convert'))->required()->searchable()
                                    ->options(fn (): array => ($user = auth()->user()) instanceof User ? app(ConvertAccess::class)->visible(Convert::query(), $user)->orderBy('full_name')->limit(500)->get()
                                        ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($user, $convert)])->all() : []),
                                Textarea::make('referral_reason')->label(__('international.fields.referral_reason'))->required()->maxLength(2000),
                                CheckboxList::make('information_scope')->label(__('international.fields.information_scope'))->required()->columns(2)
                                    ->options(collect(SharedField::cases())->mapWithKeys(fn (SharedField $field): array => [$field->value => $field->label()])->all()),
                            ]),
                            Fieldset::make(__('forms.location'))->columns(2)->schema([
                                Select::make('destination_country_id')->label(__('international.fields.destination_country'))->required()->searchable()->live()
                                    ->options(fn (): array => LocalizedName::options(Country::query())),
                                TextInput::make('destination_city')->label(__('international.fields.destination_city'))->maxLength(255),
                            ]),
                            Fieldset::make(__('forms.contact'))->columns(2)->schema([
                                Select::make('external_organization_id')->label(__('international.fields.organization'))->searchable()
                                    ->options(fn (Get $get): array => ExternalIslamicOrganization::query()->where('verification_status', '!=', OrganizationStatus::Inactive)
                                        ->when(! blank($get('destination_country_id')), fn ($query) => $query->where('country_id', (int) $get('destination_country_id')))
                                        ->orderBy('organization_name')->pluck('organization_name', 'id')->all()),
                                TextInput::make('external_contact_name')->label(__('international.fields.external_contact_name'))->maxLength(255),
                                TextInput::make('external_contact_phone')->label(__('international.fields.external_contact_phone'))->tel()->maxLength(40),
                            ]),
                        ]),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => $workflow()->create(Convert::query()->findOrFail((int) $data['convert_id']), $data, $user), 'international.notifications.saved')),
            ])
            ->recordActions([
                Action::make('packet')->label(__('international.actions.packet'))->icon('heroicon-o-eye')->modalSubmitAction(false)
                    ->modalContent(fn (InternationalReferral $record): HtmlString => new HtmlString('<dl class="grid grid-cols-2 gap-2 text-sm">'.collect(app(ReferralPacket::class)->for($record))
                        ->map(fn (?string $value, string $field): string => '<dt class="text-gray-500">'.e(SharedField::tryFrom($field)?->label() ?? $field).'</dt><dd>'.e($value ?? '—').'</dd>')->implode('').'</dl>')),
                $step('check_consent', $at(ReferralStatus::Draft, ReferralStatus::PendingConsent), fn (InternationalReferral $record, array $data, User $user) => $workflow()->checkConsent($record, $user)),
                $step('send', fn (InternationalReferral $record): bool => $record->status === ReferralStatus::Ready && $can('international.referrals.send'),
                    fn (InternationalReferral $record, array $data, User $user) => $workflow()->send($record, $user))->color('primary')->requiresConfirmation(),
                $step('acknowledge', fn (InternationalReferral $record): bool => $record->status === ReferralStatus::Sent && $can('international.referrals.send'),
                    fn (InternationalReferral $record, array $data, User $user) => $workflow()->acknowledge($record, $user))->color('success'),
                $step('activate', fn (InternationalReferral $record): bool => $record->status === ReferralStatus::Acknowledged && $can('international.referrals.send'),
                    fn (InternationalReferral $record, array $data, User $user) => $workflow()->activate($record, $user)),
                $step('decline', fn (InternationalReferral $record): bool => $record->status === ReferralStatus::Sent && $can('international.referrals.send'),
                    fn (InternationalReferral $record, array $data, User $user) => $workflow()->decline($record, $data['reason'] ?? null, $user))->schema([$reason])->color('danger'),
                $step('close', fn (InternationalReferral $record): bool => $record->status->canTransitionTo(ReferralStatus::Closed)
                    && ($can('international.referrals.send') || $record->created_by === auth()->id()),
                    fn (InternationalReferral $record, array $data, User $user) => $workflow()->close($record, $data['reason'] ?? null, $user))->schema([$reason])->color('danger'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReferrals::route('/')];
    }
}
