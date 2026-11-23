<?php

declare(strict_types=1);

use App\Support\Logging\RedactPersonalData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

it('writes logs without personal data', function (): void {
    $path = storage_path('logs/redaction-test.log');
    File::delete($path);
    config(['logging.channels.redaction' => ['driver' => 'single', 'path' => $path, 'tap' => [RedactPersonalData::class]]]);

    Log::channel('redaction')->warning('Login failed for fatima@example.com', ['phone' => '0559876543', 'nested' => ['iqamah' => '2987654321']]);

    $written = (string) File::get($path);
    expect($written)->not->toContain('fatima@example.com')->not->toContain('0559876543')->not->toContain('2987654321')
        ->toContain('[email]');
    File::delete($path);
});
