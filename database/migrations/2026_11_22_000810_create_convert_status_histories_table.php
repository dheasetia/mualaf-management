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
        Schema::create('convert_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            // The office that wrote the row; history follows the convert (03 §3.3).
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6);

            $table->index(['convert_id', 'created_at']);
        });

        AppendOnlyTables::createTriggers('convert_status_histories');
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('convert_status_histories is never dropped in production.');
        }

        AppendOnlyTables::dropTriggers('convert_status_histories');
        Schema::dropIfExists('convert_status_histories');
    }
};
