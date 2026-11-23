<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6);

            $table->index(['prospect_id', 'created_at']);
        });

        AppendOnlyTables::createTriggers('prospect_status_histories');
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('prospect_status_histories is never dropped in production.');
        }

        AppendOnlyTables::dropTriggers('prospect_status_histories');
        Schema::dropIfExists('prospect_status_histories');
    }
};
