<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Dais\Pages;

use App\Domain\Dai\Actions\CreateDai as CreateDaiAction;
use App\Domain\Dai\Data\DaiData;
use App\Filament\Center\Resources\Dais\DaiResource;
use App\Filament\Concerns\ResolvesActor;
use App\Support\Exceptions\RuleViolation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CreateDai extends CreateRecord
{
    use ResolvesActor;

    protected static string $resource = DaiResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateDaiAction::class)->handle(DaiData::fromForm($data), $this->actor());
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['data.'.$violation->field => $violation->getMessage()]);
        }
    }
}
