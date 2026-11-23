<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects\Pages;

use App\Domain\Prospects\Actions\UpdateProspect;
use App\Domain\Prospects\Data\ProspectData;
use App\Domain\Prospects\Models\Prospect;
use App\Filament\Center\Resources\Prospects\ProspectResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property Prospect $record
 */
final class EditProspect extends EditRecord
{
    use ResolvesActor;

    protected static string $resource = ProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['phone'] = $this->record->phone_encrypted;
        $data['email'] = $this->record->email_encrypted;

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateProspect::class)->handle($this->record, ProspectData::fromForm($data), $this->actor());
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
