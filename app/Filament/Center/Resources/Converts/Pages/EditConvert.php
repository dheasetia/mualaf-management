<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\Pages;

use App\Domain\Converts\Actions\UpdateConvert;
use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Models\Convert;
use App\Filament\Center\Resources\Converts\ConvertResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property Convert $record
 */
final class EditConvert extends EditRecord
{
    use ResolvesActor;

    protected static string $resource = ConvertResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateConvert::class)->handle($this->record, ConvertProfileData::fromForm($data), $this->actor());
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
