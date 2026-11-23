<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidancePrograms\RelationManagers;

use App\Domain\Guidance\Actions\ManagePrograms;
use App\Domain\Guidance\Models\GuidanceModule;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Modules with their lessons; editable only on the office's own programs.
 */
final class ModulesRelationManager extends RelationManager
{
    protected static string $relationship = 'modules';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('guidance.modules');
    }

    public function isReadOnly(): bool
    {
        $program = $this->getOwnerRecord();

        return ! ($program instanceof GuidanceProgram && (auth()->user()?->can('update', $program) ?? false));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label(__('guidance.fields.title'))->required()->maxLength(255),
            TextInput::make('sort_order')->label(__('guidance.fields.sort_order'))->integer()->default(0),
            TextInput::make('weight')->label(__('guidance.fields.weight'))->numeric()->minValue(0)->default(1),
            Toggle::make('required')->label(__('guidance.fields.required'))->default(true),
            Textarea::make('description')->label(__('guidance.fields.description'))->maxLength(2000)->columnSpanFull(),
            Repeater::make('lesson_rows')->label(__('guidance.fields.lessons'))->columnSpanFull()->reorderable()
                ->schema([
                    Hidden::make('id'),
                    TextInput::make('title')->label(__('guidance.fields.title'))->required()->maxLength(255),
                    Textarea::make('learning_objectives')->label(__('guidance.fields.learning_objectives'))->maxLength(2000),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#'),
                TextColumn::make('title')->label(__('guidance.fields.title')),
                TextColumn::make('lessons_count')->label(__('guidance.fields.lessons'))->counts('lessons'),
                IconColumn::make('required')->label(__('guidance.fields.required'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->using(fn (array $data): Model => $this->save(null, $data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, GuidanceModule $record): array => [
                        ...$data,
                        'lesson_rows' => $record->lessons()->get(['id', 'title', 'learning_objectives'])->toArray(),
                    ])
                    ->using(fn (Model $record, array $data): Model => $record instanceof GuidanceModule ? $this->save($record, $data) : $record),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    private function save(?GuidanceModule $module, array $data): GuidanceModule
    {
        $program = $this->getOwnerRecord();
        $user = auth()->user();

        if (! $program instanceof GuidanceProgram || ! $user instanceof User) {
            abort(403);
        }

        $lessons = array_values(array_map(fn (array $row): array => [
            'id' => blank($row['id'] ?? null) ? null : (int) $row['id'],
            'title' => (string) $row['title'],
            'learning_objectives' => $row['learning_objectives'] ?? null,
        ], (array) ($data['lesson_rows'] ?? [])));

        try {
            return app(ManagePrograms::class)->saveModule($program, $module, $data, $lessons, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }
}
