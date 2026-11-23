<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\Platform\Models\Country;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Reporting\Services\DailyStatistics;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 15 §15.1: synthetic data for load tests on staging, never production.
 * One office LOAD with LOAD_DAIS da'i, LOAD_CONVERTS converts (each with
 * follow-up schedules) and LOAD_PROSPECTS prospects, then a year of
 * statistics for the dashboards.
 */
final class LoadTestSeeder extends Seeder
{
    public function run(TenantContext $tenantContext, DailyStatistics $statistics): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LoadTestSeeder must not run in production.');
        }

        $tenant = Tenant::query()->where('code', 'LOAD')->first() ?? Tenant::factory()->active()->create(['code' => 'LOAD', 'name_en' => 'Load test center']);
        $author = User::factory()->create(['email' => 'load-test-author@example.invalid']);
        $countries = Country::query()->pluck('id')->whenEmpty(fn () => collect([Country::factory()->create()->id]))->all();

        $tenantContext->run($tenant, function () use ($author, $countries): void {
            $dais = Dai::factory()->count((int) config('load_test.dais'))->create();

            foreach (array_chunk(range(1, (int) config('load_test.converts')), 500) as $chunk) {
                foreach ($chunk as $index) {
                    $convert = Convert::factory()->create([
                        'primary_dai_id' => $dais->random()->id,
                        'nationality_id' => $countries[array_rand($countries)],
                        'created_by' => $author->id,
                        'created_at' => CarbonImmutable::now()->subDays($index % 365),
                    ]);
                    ConvertFollowUpSchedule::factory()->count(3)->create([
                        'convert_id' => $convert->id,
                        'dai_id' => $convert->primary_dai_id,
                        'scheduled_at' => CarbonImmutable::now()->addDays(($index % 120) - 30),
                    ]);
                }
            }

            Prospect::factory()->count((int) config('load_test.prospects'))->create(['primary_dai_id' => fn () => $dais->random()->id, 'created_by' => $author->id]);
        });

        foreach (range(0, 364) as $back) {
            $statistics->aggregate(CarbonImmutable::now()->subDays($back));
        }
    }
}
