<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11-data-model-platform §11.2. tenant_id 0 = national sequence (unique keys treat NULLs as distinct).
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0);
            $table->string('sequence_type', 30);
            $table->string('period', 10);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'sequence_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
