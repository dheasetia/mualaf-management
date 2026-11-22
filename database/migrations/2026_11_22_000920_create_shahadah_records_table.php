<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.1. shahadah_witnesses waits for open question #2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shahadah_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_registration_id')->constrained()->restrictOnDelete();
            $table->string('record_type', 30)->default('first');
            $table->date('shahadah_date');
            $table->time('shahadah_time')->nullable();
            $table->string('location');
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('preacher_dai_id')->nullable()->constrained('dais')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 20)->default('recorded');
            $table->string('void_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // One first shahadah per convert unless voided.
            $table->unsignedBigInteger('active_first_key')
                ->nullable()
                ->storedAs("IF(record_type = 'first' AND status <> 'voided', convert_id, NULL)");
            $table->unique('active_first_key', 'uq_shahadah_records_one_first');
            $table->index(['tenant_id', 'shahadah_date']);
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('shahadah_records is never dropped in production.');
        }

        Schema::dropIfExists('shahadah_records');
    }
};
