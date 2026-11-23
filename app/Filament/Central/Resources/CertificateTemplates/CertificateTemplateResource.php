<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\CertificateTemplates;

use App\Domain\Certificates\Actions\ManageTemplates;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Filament\Central\Resources\CertificateTemplates\Pages\ManageCertificateTemplates;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * National certificate templates (documents.templates.manage): drafts,
 * new versions and approval by a second person.
 */
final class CertificateTemplateResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = CertificateTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('certificates.templates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates.templates.plural');
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
        return $schema->columns(2)->components([
            TextInput::make('template_code')->label(__('certificates.fields.template_code'))->required()->maxLength(40),
            TextInput::make('name')->label(__('certificates.fields.name'))->required()->maxLength(255),
            Select::make('certificate_type')->label(__('certificates.fields.type'))->required()
                ->options(collect(CertificateType::cases())->mapWithKeys(fn (CertificateType $type): array => [$type->value => $type->label()])->all()),
            Select::make('orientation')->label(__('certificates.fields.orientation'))->required()->default('landscape')
                ->options(['landscape' => 'Landscape', 'portrait' => 'Portrait']),
            Textarea::make('html_template')->label(__('certificates.fields.html_template'))->required()->rows(20)
                ->helperText(__('certificates.help.html_template'))->columnSpanFull()->extraInputAttributes(['dir' => 'ltr', 'style' => 'font-family: monospace']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('template_code')
            ->columns([
                TextColumn::make('template_code')->label(__('certificates.fields.template_code')),
                TextColumn::make('template_version')->label(__('certificates.fields.version')),
                TextColumn::make('certificate_type')->label(__('certificates.fields.type'))->formatStateUsing(fn (CertificateType $state): string => $state->label()),
                IconColumn::make('approved_at')->label(__('certificates.fields.approved'))->boolean()->state(fn (CertificateTemplate $record): bool => $record->isApproved()),
                IconColumn::make('active')->label(__('certificates.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (CertificateTemplate $record): bool => ! $record->isApproved())
                    ->using(fn (Model $record, array $data): Model => $record instanceof CertificateTemplate ? self::save($record, $data) : $record),
                Action::make('newVersion')->label(__('certificates.actions.new_version'))
                    ->visible(fn (CertificateTemplate $record): bool => $record->isApproved())
                    ->action(self::domainAction(fn (CertificateTemplate $record, array $data, User $user) => app(ManageTemplates::class)->newVersion($record, $user), 'certificates.notifications.saved')),
                Action::make('approve')->label(__('certificates.actions.approve_template'))->color('success')->requiresConfirmation()
                    ->visible(fn (CertificateTemplate $record): bool => ! $record->isApproved())
                    ->action(self::domainAction(fn (CertificateTemplate $record, array $data, User $user) => app(ManageTemplates::class)->approve($record, $user), 'certificates.notifications.saved')),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?CertificateTemplate $template, array $data): CertificateTemplate
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManageTemplates::class)->saveDraft($template, [
                'template_code' => (string) $data['template_code'],
                'certificate_type' => (string) $data['certificate_type'],
                'name' => (string) $data['name'],
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
            'index' => ManageCertificateTemplates::route('/'),
        ];
    }
}
