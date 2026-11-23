<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open question #9 (WhatsApp): the user's own opt-in for WhatsApp alerts,
 * and integration_logs (11-data-model-platform §11.6), append-only, never
 * holding secrets, phone numbers or message text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('phone_encrypted');
        });

        Schema::create('integration_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('integration_name', 40);
            $table->string('direction', 10);
            $table->string('endpoint_reference', 100);
            $table->string('request_reference', 120)->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('status', 20);
            $table->string('error_message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration_name', 'created_at']);
        });

        AppendOnlyTables::createTriggers('integration_logs');
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('integration_logs is never dropped in production.');
        }

        AppendOnlyTables::dropTriggers('integration_logs');
        Schema::dropIfExists('integration_logs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_opt_in_at');
        });
    }
};
