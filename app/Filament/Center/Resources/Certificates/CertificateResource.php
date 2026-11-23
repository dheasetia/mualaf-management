<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Certificates;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Certificates\Actions\CertificateWorkflow;
use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Certificates\Pages\ManageCertificates;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CertificateResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('certificates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('guidance.navigation_group');
    }

    public static function table(Table $table): Table
    {
        $can = fn (string $ability, Certificate $record): bool => auth()->user()?->can($ability, $record) ?? false;
        $workflow = fn (): CertificateWorkflow => app(CertificateWorkflow::class);

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('certificate_number')->label(__('certificates.fields.certificate_number'))->searchable()->placeholder('—')->fontFamily('mono'),
                TextColumn::make('certificate_type')->label(__('certificates.fields.type'))->formatStateUsing(fn (CertificateType $state): string => $state->label()),
                TextColumn::make('convert')->label(__('certificates.fields.convert'))->state(fn (Certificate $record): string => self::convertName($record->convert_id)),
                TextColumn::make('status')->label(__('certificates.fields.status'))->badge()
                    ->formatStateUsing(fn (CertificateStatus $state): string => $state->label())
                    ->color(fn (CertificateStatus $state): string => $state->color()),
                TextColumn::make('pdf')->label(__('certificates.fields.pdf'))
                    ->state(fn (Certificate $record): ?string => $record->issued_at === null ? null : (string) ($record->hasPdf() ? __('certificates.pdf.ready') : __('certificates.pdf.processing')))
                    ->placeholder('—'),
                TextColumn::make('issued_at')->label(__('certificates.fields.issued_on'))->date('Y-m-d', timezone: (string) config('app.display_timezone'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('certificates.fields.status'))
                    ->options(collect(CertificateStatus::cases())->mapWithKeys(fn (CertificateStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->headerActions([
                Action::make('request')->label(__('certificates.actions.request'))
                    ->visible(fn (): bool => auth()->user()?->hasPermission('certificates.request') ?? false)
                    ->schema([
                        Select::make('certificate_type')->label(__('certificates.fields.type'))->required()->live()
                            ->options(collect(CertificateType::cases())->mapWithKeys(fn (CertificateType $type): array => [$type->value => $type->label()])->all()),
                        Select::make('convert_id')->label(__('certificates.fields.convert'))->required()->searchable()
                            ->visible(fn (Get $get): bool => $get('certificate_type') === CertificateType::Shahadah->value)
                            ->options(fn (): array => self::convertOptions()),
                        Select::make('enrollment_id')->label(__('certificates.fields.enrollment'))->required()->searchable()
                            ->visible(fn (Get $get): bool => $get('certificate_type') === CertificateType::GuidanceCompletion->value)
                            ->options(fn (): array => app(EnrollmentDirectory::class)->completed()
                                ->mapWithKeys(fn (int $convertId, int $enrollmentId): array => [$enrollmentId => self::convertName($convertId)])->all()),
                    ])
                    ->action(self::domainAction(function (mixed $record, array $data, User $user): void {
                        $type = CertificateType::from((string) $data['certificate_type']);
                        $enrollmentId = blank($data['enrollment_id'] ?? null) ? null : (int) $data['enrollment_id'];
                        $convertId = $type === CertificateType::Shahadah
                            ? (int) $data['convert_id']
                            : (int) app(EnrollmentDirectory::class)->convertOf((int) $enrollmentId);

                        app(CertificateWorkflow::class)->request($type, $convertId, $enrollmentId, $user);
                    }, 'certificates.notifications.saved')),
            ])
            ->recordActions([
                Action::make('approve')->label(__('certificates.actions.approve'))->color('success')
                    ->visible(fn (Certificate $record): bool => $can('approve', $record))
                    ->action(self::domainAction(fn (Certificate $record, array $data, User $user) => $workflow()->approve($record, $user), 'certificates.notifications.saved')),
                Action::make('reject')->label(__('certificates.actions.reject'))->color('danger')
                    ->visible(fn (Certificate $record): bool => $can('approve', $record))
                    ->schema([Textarea::make('reason')->label(__('certificates.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (Certificate $record, array $data, User $user) => $workflow()->reject($record, $user, $data['reason'] ?? null), 'certificates.notifications.saved')),
                Action::make('issue')->label(__('certificates.actions.issue'))->color('success')->requiresConfirmation()
                    ->visible(fn (Certificate $record): bool => $can('issue', $record))
                    ->action(self::domainAction(fn (Certificate $record, array $data, User $user) => $workflow()->issue($record, $user), 'certificates.notifications.saved')),
                Action::make('download')->label(__('certificates.actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Certificate $record): bool => $can('download', $record))
                    ->action(fn (Certificate $record): StreamedResponse => self::download($record)),
                Action::make('regenerate')->label(__('certificates.actions.regenerate'))->color('gray')->requiresConfirmation()
                    ->visible(fn (Certificate $record): bool => $record->status === CertificateStatus::Issued && (auth()->user()?->hasPermission('certificates.issue') ?? false))
                    ->action(self::domainAction(fn (Certificate $record, array $data, User $user) => $workflow()->regeneratePdf($record, $user), 'certificates.notifications.saved')),
                Action::make('revoke')->label(__('certificates.actions.revoke'))->color('danger')
                    ->visible(fn (Certificate $record): bool => $can('revoke', $record))
                    ->schema([Textarea::make('reason')->label(__('certificates.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (Certificate $record, array $data, User $user) => $workflow()->revoke($record, $user, $data['reason'] ?? null), 'certificates.notifications.saved')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCertificates::route('/'),
        ];
    }

    /** Private file, streamed to an authorized user; every download is audited. */
    private static function download(Certificate $certificate): StreamedResponse
    {
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->requireId());
        app(AuditLogger::class)->recordLater('certificate.downloaded', newValues: ['certificate_id' => $certificate->id]);

        return Storage::disk((string) config('certificates.disk'))->download($certificate->pdfPath($tenant->uuid), $certificate->certificate_number.'.pdf');
    }

    /** @return array<int, string> */
    private static function convertOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return app(ConvertAccess::class)->visible(Convert::query(), $user)->orderBy('full_name')->limit(500)->get()
            ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($user, $convert)])->all();
    }

    private static function convertName(int $convertId): string
    {
        $user = auth()->user();
        $convert = Convert::query()->find($convertId);

        return $user instanceof User && $convert instanceof Convert ? app(ConvertAccess::class)->displayName($user, $convert) : '—';
    }
}
