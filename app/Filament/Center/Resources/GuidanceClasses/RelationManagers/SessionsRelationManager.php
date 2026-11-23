<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses\RelationManagers;

use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Guidance\Actions\ClassRecords;
use App\Domain\Guidance\Enums\AttendanceStatus;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Enums\SessionStatus;
use App\Domain\Guidance\Models\Attendance;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\ClassTeacherAssignment;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceLesson;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SessionsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'sessions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('guidance.sessions');
    }

    /** Actions here check their own permissions; the view page must not hide them. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');
        $teaches = fn (): bool => self::user() !== null && self::user()->can('teach', $this->ownerClass());

        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('lesson'))
            ->columns([
                TextColumn::make('starts_at')->label(__('guidance.fields.starts_at'))->dateTime('Y-m-d H:i', timezone: $timezone),
                TextColumn::make('lesson.title')->label(__('guidance.fields.lesson'))->placeholder('—'),
                TextColumn::make('status')->label(__('guidance.fields.status'))->badge()
                    ->formatStateUsing(fn (SessionStatus $state): string => $state->label()),
            ])
            ->headerActions([
                Action::make('schedule')->label(__('guidance.sessions'))->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $teaches() || (self::user()?->hasPermission('guidance.classes.manage') ?? false))
                    ->schema([
                        DateTimePicker::make('starts_at')->label(__('guidance.fields.starts_at'))->required()->timezone($timezone)->seconds(false),
                        DateTimePicker::make('ends_at')->label(__('guidance.fields.ends_at'))->required()->timezone($timezone)->seconds(false)->after('starts_at'),
                        Select::make('lesson_id')->label(__('guidance.fields.lesson'))->searchable()
                            ->options(fn (): array => GuidanceLesson::query()
                                ->whereHas('module', fn (Builder $query) => $query->where('program_id', $this->ownerClass()->program_id))
                                ->orderBy('module_id')->orderBy('sort_order')->pluck('title', 'id')->all()),
                        Select::make('teacher_user_id')->label(__('guidance.fields.teacher'))
                            ->visible(fn (): bool => self::user()?->hasPermission('guidance.classes.manage') ?? false)
                            ->options(fn (): array => ClassTeacherAssignment::query()->where('class_id', $this->ownerClass()->id)->whereNull('released_at')
                                ->with('user')->get()->mapWithKeys(fn (ClassTeacherAssignment $assignment): array => [$assignment->user_id => $assignment->user->name])->all()),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ClassRecords::class)->scheduleSession($this->ownerClass(), [
                        'starts_at' => (string) $data['starts_at'],
                        'ends_at' => (string) $data['ends_at'],
                        'lesson_id' => blank($data['lesson_id'] ?? null) ? null : (int) $data['lesson_id'],
                        'teacher_user_id' => blank($data['teacher_user_id'] ?? null) ? null : (int) $data['teacher_user_id'],
                    ], $user))),
            ])
            ->recordActions([
                Action::make('attendance')->label(__('guidance.actions.take_attendance'))->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (ClassSession $record): bool => $record->status !== SessionStatus::Cancelled && $teaches()
                        && (self::user()?->hasPermission('guidance.attendance.record') ?? false))
                    ->fillForm(fn (ClassSession $record): array => ['rows' => $this->attendanceRows($record)])
                    ->schema([
                        Repeater::make('rows')->hiddenLabel()->addable(false)->deletable(false)->reorderable(false)->columns(2)
                            ->schema([
                                Hidden::make('enrollment_id'),
                                TextInput::make('name')->hiddenLabel()->disabled()->dehydrated(false),
                                Select::make('status')->hiddenLabel()->required()->options(collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $case): array => [$case->value => $case->label()])->all()),
                            ]),
                    ])
                    ->action(self::domainAction(fn (ClassSession $record, array $data, User $user) => app(ClassRecords::class)->recordAttendance(
                        $record,
                        collect((array) $data['rows'])->mapWithKeys(fn (array $row): array => [(int) $row['enrollment_id'] => AttendanceStatus::from((string) $row['status'])])->all(),
                        $user,
                    ))),
                Action::make('cancel')->label(__('guidance.actions.cancel_session'))->color('gray')->requiresConfirmation()
                    ->visible(fn (ClassSession $record): bool => $record->status === SessionStatus::Scheduled && ($teaches() || (self::user()?->hasPermission('guidance.classes.manage') ?? false)))
                    ->action(self::domainAction(fn (ClassSession $record, array $data, User $user) => app(ClassRecords::class)->cancelSession($record, $user))),
            ]);
    }

    /** @return list<array{enrollment_id: int, name: string, status: string}> */
    private function attendanceRows(ClassSession $session): array
    {
        $recorded = Attendance::query()->where('class_session_id', $session->id)->pluck('status', 'enrollment_id');
        $user = self::user();

        return array_values(Enrollment::query()
            ->where('class_id', $session->class_id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Active, EnrollmentStatus::Paused])
            ->with('convert')
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'name' => $user === null ? '—' : app(ConvertAccess::class)->displayName($user, $enrollment->convert),
                'status' => ($recorded[$enrollment->id] ?? AttendanceStatus::Present) instanceof AttendanceStatus
                    ? ($recorded[$enrollment->id] ?? AttendanceStatus::Present)->value
                    : (string) $recorded[$enrollment->id],
            ])
            ->all());
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function ownerClass(): GuidanceClass
    {
        $class = $this->getOwnerRecord();

        return $class instanceof GuidanceClass ? $class : abort(404);
    }
}
