<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use App\Support\Identity\PhoneNumber;
use Carbon\CarbonImmutable;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Filament profile plus the user's mobile number and their own opt-in for
 * WhatsApp alerts (open question #9). The number is stored as +digits,
 * encrypted; changing the opt-in is audited without the number.
 */
class EditProfile extends BaseEditProfile
{
    public function mount(): void
    {
        parent::mount();

        $this->redirectToOfficeProfile();
    }

    /**
     * Center: this office-less page cannot show the sidebar, so open the profile
     * inside the user's office (OfficeProfile) instead.
     */
    private function redirectToOfficeProfile(): void
    {
        $panel = Filament::getCurrentPanel();
        $routeName = $panel?->generateRouteName('office-profile');

        if (! static::isSimple() || $panel === null || ! $panel->hasTenancy() || ! Route::has((string) $routeName)) {
            return;
        }

        $tenant = Filament::getUserDefaultTenant($this->getUser());

        if ($tenant !== null) {
            $this->redirect(route((string) $routeName, ['tenant' => $tenant]));
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            TextInput::make('phone')->label(__('identity.profile.phone'))->tel()->maxLength(30)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->required(fn (Get $get): bool => (bool) $get('whatsapp_opt_in')),
            Toggle::make('whatsapp_opt_in')->label(__('identity.profile.whatsapp_opt_in'))->live()
                ->helperText(__('identity.profile.whatsapp_help')),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $data['phone'] = $user->phone_encrypted;
            $data['whatsapp_opt_in'] = $user->whatsapp_opt_in_at !== null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $phone = blank($data['phone'] ?? null) ? null : PhoneNumber::normalize((string) $data['phone']);
        $optIn = (bool) ($data['whatsapp_opt_in'] ?? false);
        unset($data['phone'], $data['whatsapp_opt_in']);

        $this->applyContact($record, $phone, $optIn);

        return parent::handleRecordUpdate($record, $data);
    }

    private function applyContact(Model $record, ?string $phone, bool $optIn): void
    {
        if (! $record instanceof User) {
            return;
        }

        if ($phone === null && filled($this->data['phone'] ?? null)) {
            throw ValidationException::withMessages(['data.phone' => __('identity.profile.invalid_phone')]);
        }

        $wasOptedIn = $record->whatsapp_opt_in_at !== null;
        $record->phone_encrypted = $phone === null ? null : '+'.$phone;
        $record->whatsapp_opt_in_at = $optIn && $phone !== null ? ($record->whatsapp_opt_in_at ?? CarbonImmutable::now()) : null;
        $record->save();

        if ($wasOptedIn !== ($record->whatsapp_opt_in_at !== null)) {
            app(AuditLogger::class)->record('account.whatsapp_opt_in_changed', $record, newValues: ['opted_in' => ! $wasOptedIn], actor: $record);
        }
    }
}
