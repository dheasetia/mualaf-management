<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.1 and 12 §12.2: a registration returned for
 * correction remembers who corrected it (never its verifier), and a voided
 * shahadah needs a request by the office and approval in Central.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convert_registrations', function (Blueprint $table): void {
            $table->foreignId('corrected_by')->nullable()->after('registered_by')->constrained('users')->restrictOnDelete();
        });

        Schema::create('shahadah_void_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // One open request per registration.
            $table->unsignedBigInteger('pending_key')->nullable()
                ->storedAs("IF(status = 'pending', convert_registration_id, NULL)");
            $table->unique('pending_key', 'uq_shahadah_void_requests_one_pending');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('shahadah_void_requests is never dropped in production.');
        }

        Schema::dropIfExists('shahadah_void_requests');

        Schema::table('convert_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('corrected_by');
        });
    }
};
