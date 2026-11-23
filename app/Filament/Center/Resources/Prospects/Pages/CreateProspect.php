<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects\Pages;

use App\Domain\Prospects\Actions\CreateProspect as CreateProspectAction;
use App\Domain\Prospects\Data\ProspectData;
use App\Filament\Center\Resources\Prospects\ProspectResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CreateProspect extends CreateRecord
{
    use ResolvesActor;

    protected static string $resource = ProspectResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateProspectAction::class)->handle(ProspectData::fromForm($data), $this->actor());
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
