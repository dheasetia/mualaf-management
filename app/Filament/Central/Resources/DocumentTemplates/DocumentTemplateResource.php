<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\DocumentTemplates;

use App\Domain\Documents\Actions\ManageDocumentTemplates;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Filament\Central\Resources\DocumentTemplates\Pages\ManageDocumentTemplatesPage;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * National letter templates (documents.templates.manage): drafts, new
 * versions and approval by a second person.
 */
final class DocumentTemplateResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = DocumentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 91;

    public static function getModelLabel(): string
    {
        return __('documents.templates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('documents.templates.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('tenant_id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('documents.templates.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('template_code')->label(__('documents.fields.template_code'))->required()->maxLength(40),
                    TextInput::make('name_ar')->label(__('documents.fields.name'))->required()->maxLength(255),
                    Select::make('document_type')->label(__('documents.fields.type'))->required()
                        ->options(collect(LetterType::cases())->mapWithKeys(fn (LetterType $type): array => [$type->value => $type->label()])->all()),
                    Select::make('orientation')->label(__('documents.fields.orientation'))->required()->default('portrait')
                        ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape']),
                    TextInput::make('description')->label(__('documents.fields.description'))->maxLength(255)->columnSpanFull(),
                ]),
                Fieldset::make(__('forms.content'))->columns(1)->schema([
                    Textarea::make('html_template')->label(__('documents.fields.html_template'))->required()->rows(20)
                        ->helperText(__('documents.help.html_template'))->columnSpanFull()->extraInputAttributes(['dir' => 'ltr', 'style' => 'font-family: monospace']),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('template_code')
            ->columns([
                TextColumn::make('template_code')->label(__('documents.fields.template_code')),
                TextColumn::make('version')->label(__('documents.fields.version')),
                TextColumn::make('document_type')->label(__('documents.fields.type'))->formatStateUsing(fn (LetterType $state): string => $state->label()),
                IconColumn::make('approved_at')->label(__('documents.fields.approved'))->boolean()->state(fn (DocumentTemplate $record): bool => $record->isApproved()),
                IconColumn::make('active')->label(__('documents.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (DocumentTemplate $record): bool => ! $record->isApproved())
                    ->using(fn (Model $record, array $data): Model => $record instanceof DocumentTemplate ? self::save($record, $data) : $record),
                Action::make('newVersion')->label(__('documents.actions.new_template_version'))
                    ->visible(fn (DocumentTemplate $record): bool => $record->isApproved())
                    ->action(self::domainAction(fn (DocumentTemplate $record, array $data, User $user) => app(ManageDocumentTemplates::class)->newVersion($record, $user), 'documents.notifications.saved')),
                Action::make('approve')->label(__('documents.actions.approve_template'))->color('success')->requiresConfirmation()
                    ->visible(fn (DocumentTemplate $record): bool => ! $record->isApproved())
                    ->action(self::domainAction(fn (DocumentTemplate $record, array $data, User $user) => app(ManageDocumentTemplates::class)->approve($record, $user), 'documents.notifications.saved')),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?DocumentTemplate $template, array $data): DocumentTemplate
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManageDocumentTemplates::class)->saveDraft($template, [
                'template_code' => (string) $data['template_code'],
                'document_type' => (string) $data['document_type'],
                'name_ar' => (string) $data['name_ar'],
                'description' => blank($data['description'] ?? null) ? null : (string) $data['description'],
                'html_template' => (string) $data['html_template'],
                'orientation' => (string) $data['orientation'],
            ], $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentTemplatesPage::route('/'),
        ];
    }
}
