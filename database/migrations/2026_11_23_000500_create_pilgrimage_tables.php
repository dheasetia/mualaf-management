<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10-data-model-administration §10.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilgrimage_programs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('program_type', 10);
            $table->string('name');
            $table->unsignedSmallInteger('season_year');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('registration_deadline');
            $table->unsignedInteger('capacity');
            $table->json('eligibility_rules');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('pilgrimage_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('pilgrimage_programs')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('description');
            $table->boolean('required')->default(true);
            $table->timestamps();

            $table->unique(['program_id', 'code']);
        });

        Schema::create('pilgrimage_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained('pilgrimage_programs')->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('applied');
            $table->foreignId('eligibility_checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('eligibility_checked_at')->nullable();
            $table->text('eligibility_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('consent_record_id')->nullable();
            $table->string('withdrawal_reason', 1000)->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'convert_id']);
            $table->index(['program_id', 'status']);
        });

        Schema::create('pilgrimage_registration_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained('pilgrimage_registrations')->restrictOnDelete();
            $table->foreignId('requirement_id')->constrained('pilgrimage_requirements')->restrictOnDelete();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'requirement_id'], 'uq_pilgrimage_registration_requirement');
        });

        Schema::create('pilgrimage_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('pilgrimage_programs')->restrictOnDelete();
            $table->string('group_name');
            $table->foreignId('leader_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('capacity');
            $table->timestamps();
        });

        Schema::create('pilgrimage_group_members', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('pilgrimage_groups')->restrictOnDelete();
            $table->foreignId('pilgrimage_registration_id')->unique()->constrained('pilgrimage_registrations')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['group_id', 'pilgrimage_registration_id']);
        });

        Schema::create('pilgrimage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('pilgrimage_programs')->restrictOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('pilgrimage_registrations')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('metadata');
            $table->timestamp('created_at', 6);

            $table->index(['program_id', 'created_at']);
        });

        AppendOnlyTables::createTriggers('pilgrimage_events');
    }

    public function down(): void
    {
        AppendOnlyTables::dropTriggers('pilgrimage_events');

        foreach (['pilgrimage_events', 'pilgrimage_group_members', 'pilgrimage_groups', 'pilgrimage_registration_requirements', 'pilgrimage_registrations', 'pilgrimage_requirements', 'pilgrimage_programs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
