<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10-data-model-administration §10.6–10.8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->string('consent_type', 40);
            $table->json('scope');
            $table->timestamp('given_at');
            $table->string('method', 30);
            $table->unsignedBigInteger('evidence_document_id')->nullable();
            $table->foreignId('witnessed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdrawn_reason', 1000)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['convert_id', 'consent_type']);
        });

        Schema::table('pilgrimage_registrations', function (Blueprint $table): void {
            $table->foreign('consent_record_id')->references('id')->on('consent_records')->restrictOnDelete();
        });

        Schema::create('convert_home_country_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('contact_name');
            $table->string('relationship', 20);
            $table->text('phone_encrypted')->nullable();
            $table->text('whatsapp_encrypted')->nullable();
            $table->text('email_encrypted')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city')->nullable();
            $table->foreignId('preferred_language_id')->nullable()->constrained('languages')->restrictOnDelete();
            $table->boolean('is_muslim')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('permission_to_contact')->default(false);
            $table->string('disclosure_scope', 30)->default('none');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('convert_home_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('province_state')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('village')->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('external_islamic_organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('city');
            $table->string('organization_name');
            $table->string('organization_type', 30);
            $table->string('contact_person')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('verification_status', 20)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['country_id', 'verification_status'], 'idx_external_orgs_country_status');
        });

        Schema::create('international_follow_up_referrals', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('destination_country_id')->constrained('countries')->restrictOnDelete();
            $table->string('destination_city')->nullable();
            $table->foreignId('external_organization_id')->nullable()->constrained('external_islamic_organizations', 'id', 'fk_referrals_external_organization')->restrictOnDelete();
            $table->string('external_contact_name')->nullable();
            $table->string('external_contact_phone', 40)->nullable();
            $table->text('referral_reason');
            $table->json('information_scope');
            $table->foreignId('consent_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('pre_departure_checklists', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->date('expected_departure_on')->nullable();
            $table->foreignId('destination_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('referral_id')->nullable()->constrained('international_follow_up_referrals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('pre_departure_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_id')->constrained('pre_departure_checklists')->restrictOnDelete();
            $table->string('item_code', 40);
            $table->string('status', 20)->default('pending');
            $table->foreignId('done_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['checklist_id', 'item_code']);
        });
    }

    public function down(): void
    {
        Schema::table('pilgrimage_registrations', function (Blueprint $table): void {
            $table->dropForeign(['consent_record_id']);
        });

        foreach (['pre_departure_checklist_items', 'pre_departure_checklists', 'international_follow_up_referrals', 'external_islamic_organizations', 'convert_home_locations', 'convert_home_country_contacts', 'consent_records'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
