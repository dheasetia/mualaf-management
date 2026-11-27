<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\SubmissionTypes;

use App\Domain\Administration\Actions\ManageSubmissionTypes;
use App\Domain\Administration\Models\SubmissionType;
use App\Domain\Documents\Actions\LetterWorkflow;
use App\Domain\Documents\Enums\LetterType;
use App\Filament\Central\Resources\SubmissionTypes\Pages\ManageSubmissionTypesPage;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * National catalogue of administrative submission types (master.manage).
 */
final class SubmissionTypeResource extends Resource
{
    protected static ?string $model = SubmissionType::class;

    protected static ?string $slug = 'submission-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 86;

    public static function getModelLabel(): string
    {
        return __('submissions.types.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('submissions.types.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('code')->label(__('submissions.fields.code'))->required()->maxLength(40)
                        ->disabled(fn (?SubmissionType $record): bool => $record !== null && app(ManageSubmissionTypes::class)->isInUse($record))->dehydrated(),
                    TextInput::make('name_ar')->label(__('submissions.fields.name_ar'))->required()->maxLength(255),
                    TextInput::make('name_en')->label(__('submissions.fields.name_en'))->maxLength(255),
                    Toggle::make('active')->label(__('submissions.fields.active'))->default(true),
                    Textarea::make('description')->label(__('submissions.fields.description'))->maxLength(255)->columnSpanFull(),
                ]),
                Fieldset::make(__('forms.settings'))->columns(3)->schema([
                    Select::make('generates_document_template_code')->label(__('submissions.fields.letter_type'))
                        ->options(collect(LetterWorkflow::CONVERT_LETTERS)->mapWithKeys(fn (LetterType $type): array => [$type->value => $type->label()])->all()),
                    TextInput::make('approval_stages')->label(__('submissions.fields.approval_stages'))->required()->integer()->minValue(1)->maxValue(5)->default(1),
                    TextInput::make('sla_days')->label(__('submissions.fields.sla_days'))->integer()->minValue(1)->maxValue(365),
                ]),
                Fieldset::make(__('forms.attachments'))->columns(1)->schema([
                    Repeater::make('required_documents')->label(__('submissions.fields.required_documents'))->columns(4)->columnSpanFull()->defaultItems(1)
                        ->schema([
                            TextInput::make('code')->label(__('submissions.fields.code'))->required()->maxLength(40),
                            TextInput::make('name_ar')->label(__('submissions.fields.name_ar'))->required()->maxLength(255),
                            TextInput::make('name_en')->label(__('submissions.fields.name_en'))->maxLength(255),
                            Toggle::make('required')->label(__('submissions.fields.required'))->default(true),
                        ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label(__('submissions.fields.code'))->fontFamily('mono')->searchable(),
                TextColumn::make('name_ar')->label(__('submissions.fields.name_ar'))->searchable(),
                TextColumn::make('approval_stages')->label(__('submissions.fields.approval_stages'))->state(fn (SubmissionType $record): int => $record->approvalStages()),
                TextColumn::make('sla_days')->label(__('submissions.fields.sla_days'))->state(fn (SubmissionType $record): ?int => $record->slaDays())->placeholder('—'),
                IconColumn::make('active')->label(__('submissions.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (SubmissionType $record): array => [
                        ...$record->only(['code', 'name_ar', 'name_en', 'description', 'generates_document_template_code', 'active', 'required_documents']),
                        'approval_stages' => $record->approvalStages(),
                        'sla_days' => $record->slaDays(),
                    ])
                    ->using(fn (Model $record, array $data): Model => $record instanceof SubmissionType ? self::save($record, $data) : $record),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?SubmissionType $type, array $data): SubmissionType
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManageSubmissionTypes::class)->save($type, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubmissionTypesPage::route('/')];
    }
}
