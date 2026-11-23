<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PlatformMasterSeeder::class, RbacSeeder::class, GuidanceProgramSeeder::class, CertificateTemplateSeeder::class, DocumentTemplateSeeder::class]);

        if (app()->isLocal()) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
