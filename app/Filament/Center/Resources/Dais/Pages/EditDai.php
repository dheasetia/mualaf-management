<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Dais\Pages;

use App\Domain\Dai\Actions\ChangeDaiStatus;
use App\Domain\Dai\Actions\UpdateDai;
use App\Domain\Dai\Data\DaiData;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiLanguage;
use App\Filament\Center\Resources\Dais\DaiResource;
use App\Filament\Concerns\ResolvesActor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property Dai $record
 */
final class EditDai extends EditRecord
{
    use ResolvesActor;

    protected static string $resource = DaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label(__('dai.actions.change_status'))
                ->authorize(fn (): bool => auth()->user()?->can('changeStatus', $this->record) ?? false)
                ->schema([
                    Select::make('status')->label(__('dai.fields.status'))->required()
                        ->options(fn (): array => collect($this->record->status->allowedTransitions())
                            ->mapWithKeys(fn (DaiStatus $status): array => [$status->value => $status->label()])
                            ->all()),
                    Textarea::make('reason')->label(__('dai.fields.reason'))->maxLength(500),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->record = app(ChangeDaiStatus::class)
                            ->handle($this->record, DaiStatus::from((string) $data['status']), $this->actor(), $data['reason'] ?? null);
                    } catch (DaiRuleViolation $violation) {
                        Notification::make()->danger()->title($violation->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('dai.notifications.status_changed'))->send();
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['phone'] = $this->record->phone_encrypted;
        $data['language_rows'] = DaiLanguage::query()->where('dai_id', $this->record->id)->get()
            ->map(fn (DaiLanguage $row): array => ['language_id' => $row->language_id, 'proficiency' => $row->proficiency->value])
            ->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateDai::class)->handle($this->record, DaiData::fromForm($data), $this->actor());
        } catch (DaiRuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }
}
