<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Submissions\Pages;

use App\Domain\Administration\Actions\SubmissionWorkflow;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Filament\Center\Resources\Submissions\SubmissionResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ListRecords;

final class ListSubmissions extends ListRecords
{
    use RunsDomainActions;

    protected static string $resource = SubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label(__('submissions.actions.create'))
                ->visible(fn (): bool => auth()->user()?->hasPermission('submissions.create') ?? false)
                ->schema([
                    Select::make('convert_id')->label(__('submissions.fields.convert'))->required()->searchable()
                        ->options(function (): array {
                            $user = auth()->user();

                            return $user instanceof User ? app(ConvertAccess::class)->visible(Convert::query(), $user)->orderBy('full_name')->limit(500)->get()
                                ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($user, $convert)])->all() : [];
                        }),
                    Select::make('submission_type_id')->label(__('submissions.fields.type'))->required()
                        ->options(fn (): array => LocalizedName::options(SubmissionType::query()->where('active', true))),
                    Textarea::make('notes')->label(__('submissions.fields.notes'))->maxLength(2000),
                ])
                ->action(self::domainAction(function (mixed $record, array $data, User $user): void {
                    $submission = app(SubmissionWorkflow::class)->create(
                        Convert::query()->findOrFail((int) $data['convert_id']),
                        SubmissionType::query()->findOrFail((int) $data['submission_type_id']),
                        $data['notes'] ?? null,
                        $user,
                    );
                    $this->redirect(SubmissionResource::getUrl('view', ['record' => $submission]));
                }, 'submissions.notifications.saved')),
        ];
    }
}
