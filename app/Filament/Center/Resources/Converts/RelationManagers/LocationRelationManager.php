<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Actions\SaveConvertLocationRecord;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Converts\Models\ConvertEmployment;
use App\Domain\Converts\Models\ConvertResidence;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Employment, residence and contact persons of a convert: visible with the
 * full profile, written through SaveConvertLocationRecord by holders of
 * converts.update.
 */
abstract class LocationRelationManager extends RelationManager
{
    /** @return class-string<ConvertEmployment|ConvertResidence|ConvertContactPerson> */
    abstract protected static function recordClass(): string;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Convert && (auth()->user()?->can('viewLocations', $ownerRecord) ?? false);
    }

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();

        return ! ($owner instanceof Convert && (auth()->user()?->can('update', $owner) ?? false));
    }

    protected function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data): Model => $this->save(null, $data));
    }

    protected function editAction(): EditAction
    {
        return EditAction::make()->using(fn (Model $record, array $data): Model => $this->save($record, $data));
    }

    /** @param  array<string, mixed>  $data */
    private function save(?Model $record, array $data): Model
    {
        $owner = $this->getOwnerRecord();
        $user = auth()->user();

        $isLocationRecord = $record === null || $record instanceof ConvertEmployment || $record instanceof ConvertResidence || $record instanceof ConvertContactPerson;

        if (! $owner instanceof Convert || ! $user instanceof User || ! $isLocationRecord) {
            abort(403);
        }

        try {
            return app(SaveConvertLocationRecord::class)->handle($owner, static::recordClass(), $record, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }
}
