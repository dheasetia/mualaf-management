<?php

declare(strict_types=1);

use App\Console\Commands\ExportRbacMatrix;

it('keeps the approved matrix document identical to the role catalog', function (): void {
    $document = file_get_contents(base_path('docs/tdd/03a-rbac-matrix.md'));

    expect($document)->toBe(ExportRbacMatrix::render(), 'Run `php artisan rbac:matrix` and review the diff.');
});
