<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.1. Witnesses are mandatory (open question #2,
 * decided 23-11-2026); convert_id lets rows be scoped through the convert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shahadah_witnesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shahadah_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('phone_encrypted');
            $table->char('identity_last4', 4)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('shahadah_witnesses is never dropped in production.');
        }

        Schema::dropIfExists('shahadah_witnesses');
    }
};
