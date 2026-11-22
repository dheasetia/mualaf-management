<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06-data-model-core §6.9 and 04-identity-privacy §4.3: the unique key on the
 * active fingerprint is the national guard against a second convert record
 * for one person, whatever the verification status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convert_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->string('identity_type', 40);
            $table->text('identity_number_encrypted');
            $table->char('identity_last4', 4);
            $table->binary('identity_fingerprint', 32, fixed: true);
            $table->unsignedTinyInteger('fingerprint_key_version');
            $table->foreignId('issuing_country_id')->constrained('countries')->restrictOnDelete();
            $table->date('expires_on')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('verification_status', 20)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->string('replaced_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->binary('active_fingerprint', 32, fixed: true)
                ->nullable()
                ->storedAs('IF(replaced_at IS NULL, identity_fingerprint, NULL)');
            $table->unsignedBigInteger('primary_convert_key')
                ->nullable()
                ->storedAs('IF(is_primary AND replaced_at IS NULL, convert_id, NULL)');

            $table->unique(['identity_type', 'issuing_country_id', 'active_fingerprint'], 'uq_convert_identifiers_active_fingerprint');
            $table->unique('primary_convert_key', 'uq_convert_identifiers_one_primary');
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('convert_identifiers is never dropped in production.');
        }

        Schema::dropIfExists('convert_identifiers');
    }
};
