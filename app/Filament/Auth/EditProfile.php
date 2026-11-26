<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\ProfilePhotoRejected;
use App\Domain\Identity\Services\ProfilePhotos;
use App\Models\User;
use App\Support\Identity\PhoneNumber;
use Carbon\CarbonImmutable;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SensitiveParameter;

/**
 * Filament profile plus the user's mobile number and their own opt-in for
 * WhatsApp alerts (open question #9). The number is stored as +digits,
 * encrypted; changing the opt-in is audited without the number.
 */
final class EditProfile extends BaseEditProfile
{
    /** Set while saving; the top bar avatar is another component, so the page reloads. */
    private bool $photoChanged = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('photo')->label(__('identity.profile.photo'))->helperText(__('identity.profile.photo_help'))
                ->image()->imagePreviewHeight('120')->storeFiles(false)
                ->maxSize(ProfilePhotos::MAX_BYTES / 1024)->acceptedFileTypes(ProfilePhotos::MIME_TYPES),
            Toggle::make('remove_photo')->label(__('identity.profile.remove_photo'))
                ->visible(fn (): bool => ($user = $this->getUser()) instanceof User && $user->avatar_path !== null),
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
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['phone'], $data['whatsapp_opt_in'], $data['photo'], $data['remove_photo']);

        $this->applyContact($record, $phone, $optIn);
        $this->applyPhoto($record, $photo, $removePhoto);

        return parent::handleRecordUpdate($record, $data);
    }

    protected function afterSave(): void
    {
        if ($this->photoChanged) {
            $this->js('window.location.reload()');
        }
    }

    private function applyPhoto(Model $record, mixed $photo, bool $remove): void
    {
        if (! $record instanceof User) {
            return;
        }

        $photos = app(ProfilePhotos::class);
        $photo = is_array($photo) ? (array_values($photo)[0] ?? null) : $photo;

        try {
            if ($photo instanceof TemporaryUploadedFile) {
                $photos->replace($record, (string) $photo->getRealPath());
                $this->photoChanged = true;
            } elseif ($remove && $record->avatar_path !== null) {
                $photos->remove($record);
                $this->photoChanged = true;
            }
        } catch (ProfilePhotoRejected $rejected) {
            throw ValidationException::withMessages(['data.photo' => $rejected->getMessage()]);
        }
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
