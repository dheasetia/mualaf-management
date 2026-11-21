<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class CreateTenant
{
    /**
     * Creates a tenant in draft status together with its default settings.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Tenant
    {
        return DB::transaction(function () use ($attributes): Tenant {
            $tenant = Tenant::query()->create($attributes);

            $tenant->settings()->create([
                'settings' => self::defaultSettings($tenant),
            ]);

            return $tenant;
        });
    }

    /** @return array<string, mixed> */
    public static function defaultSettings(Tenant $tenant): array
    {
        return [
            'numbering_prefix' => $tenant->code,
            'service_language_codes' => ['ar', 'en'],
            // Sunday (0) to Thursday (4).
            'working_days' => [0, 1, 2, 3, 4],
        ];
    }
}
