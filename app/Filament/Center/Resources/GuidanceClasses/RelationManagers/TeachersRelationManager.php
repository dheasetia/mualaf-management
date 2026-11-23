<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses\RelationManagers;

use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Models\ClassTeacherAssignment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TeachersRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'teacherAssignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('guidance.teachers');
    }

    /** Actions here check their own permissions; the view page must not hide them. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $manages = fn (): bool => auth()->user()?->hasPermission('guidance.classes.manage') ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('released_at')->with('user'))
            ->columns([
                TextColumn::make('user.name')->label(__('guidance.fields.teacher')),
                TextColumn::make('role')->label(__('guidance.fields.role'))->formatStateUsing(fn (TeacherRole $state): string => $state->label()),
                TextColumn::make('assigned_at')->label(__('guidance.fields.assigned_at'))->date('Y-m-d'),
            ])
            ->headerActions([
                Action::make('assign')->label(__('guidance.actions.assign_teacher'))->visible($manages)
                    ->schema([
                        Select::make('user_id')->label(__('guidance.fields.teacher'))->required()->searchable()
                            ->options(fn (): array => User::query()
                                ->whereHas('tenantMemberships', fn (Builder $query) => $query->where('tenant_id', app(TenantContext::class)->requireId())->where('status', MembershipStatus::Active))
                                ->orderBy('name')->pluck('name', 'id')->all()),
                        Select::make('role')->label(__('guidance.fields.role'))->required()->options(collect(TeacherRole::cases())->mapWithKeys(fn (TeacherRole $case): array => [$case->value => $case->label()])->all()),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManageClasses::class)
                        ->assignTeacher($this->ownerClass(), (int) $data['user_id'], TeacherRole::from((string) $data['role']), $user))),
            ])
            ->recordActions([
                Action::make('release')->label(__('guidance.actions.release'))->color('gray')->requiresConfirmation()->visible($manages)
                    ->action(self::domainAction(fn (ClassTeacherAssignment $record, array $data, User $user) => app(ManageClasses::class)->releaseTeacher($record, $user))),
            ]);
    }

    private function ownerClass(): GuidanceClass
    {
        $class = $this->getOwnerRecord();

        return $class instanceof GuidanceClass ? $class : abort(404);
    }
}
