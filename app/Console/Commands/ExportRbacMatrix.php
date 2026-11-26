<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Rbac\PermissionCatalog;
use App\Domain\Identity\Rbac\RoleCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Writes the role × permission matrix as Markdown, generated from RoleCatalog,
 * so the document reviewed and approved is exactly what the code enforces.
 */
#[Signature('rbac:matrix {--output=docs/tdd/03a-rbac-matrix.md : Path relative to the project root}')]
#[Description('Generate the role × permission matrix document from the RBAC catalog')]
final class ExportRbacMatrix extends Command
{
    /** Column headers: short, so a module table fits on screen. */
    private const array SHORT = [
        'super_admin' => 'SA', 'central_admin' => 'CA', 'central_auditor' => 'CAu',
        'central_reporting' => 'CR', 'support_officer' => 'SO',
        'center_admin' => 'CtA', 'head_of_dawah' => 'HoD', 'dai' => 'Dai',
        'registration_officer' => 'Reg', 'shahadah_verifier' => 'SV', 'guidance_coordinator' => 'GC',
        'teacher' => 'Tch', 'examination_officer' => 'EO', 'certificate_officer' => 'CO',
        'administrative_officer' => 'AO', 'approver' => 'Apr', 'transportation_officer' => 'TO',
        'pilgrimage_officer' => 'PO', 'local_auditor' => 'LA',
    ];

    public function handle(): int
    {
        $path = base_path((string) $this->option('output'));
        file_put_contents($path, self::render());
        $this->info('Written '.$this->option('output'));

        return self::SUCCESS;
    }

    public static function render(): string
    {
        $roles = RoleCatalog::roles();
        $lines = [
            '# 03a. Matriks Role × Permission',
            '',
            '> File ini dihasilkan otomatis dari `app/Domain/Identity/Rbac/RoleCatalog.php` dengan',
            '> `php artisan rbac:matrix`. Jangan diedit manual: ubah katalognya, lalu jalankan ulang perintah.',
            '> Test `RbacMatrixDocumentTest` gagal bila file ini tidak sama dengan kode.',
            '',
            'Dasar: [03-tenancy-authorization.md](03-tenancy-authorization.md) §3.5–3.7.',
            '',
            '## Ringkasan role',
            '',
            '| Kode | Role | Nama Arab | Scope | Jumlah permission |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($roles as $code => $role) {
            $permissions = array_values(array_unique($role['permissions']));
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %d |',
                self::SHORT[$code] ?? $code,
                $role['name_en'],
                $role['name_ar'],
                $role['scope'] === RoleScope::Platform ? 'Platform' : 'Kantor',
                count($permissions),
            );
        }

        $lines[] = '';
        $lines[] = '## Matriks per modul';
        $lines[] = '';
        $lines[] = '✓ = role memegang permission. Kolom pertama lima adalah role platform; sisanya role kantor.';

        $header = '| Permission | '.implode(' | ', array_map(fn (string $code): string => self::SHORT[$code] ?? $code, array_keys($roles))).' |';
        $divider = '| --- |'.str_repeat(' :-: |', count($roles));

        foreach (self::modules() as $module => $codes) {
            $lines[] = '';
            $lines[] = '### '.$module;
            $lines[] = '';
            $lines[] = $header;
            $lines[] = $divider;

            foreach ($codes as $permission) {
                $cells = array_map(
                    fn (array $role): string => in_array($permission, $role['permissions'], true) ? '✓' : '',
                    $roles,
                );
                $lines[] = '| `'.$permission.'` | '.implode(' | ', $cells).' |';
            }
        }

        $unused = array_values(array_filter(
            PermissionCatalog::CODES,
            fn (string $permission): bool => array_filter($roles, fn (array $role): bool => in_array($permission, $role['permissions'], true)) === [],
        ));

        $lines[] = '';
        $lines[] = '## Permission tanpa role';
        $lines[] = '';
        $lines[] = $unused === []
            ? 'Tidak ada: setiap permission dipegang minimal satu role.'
            : 'Permission berikut belum dipegang role mana pun: `'.implode('`, `', $unused).'`.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Permissions grouped by top-level module, in catalog order.
     *
     * @return array<string, list<string>>
     */
    private static function modules(): array
    {
        $modules = [];

        foreach (PermissionCatalog::CODES as $code) {
            $modules[explode('.', $code)[0]][] = $code;
        }

        return $modules;
    }
}
