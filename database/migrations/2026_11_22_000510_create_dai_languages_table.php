<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dai_languages', function (Blueprint $table): void {
            $table->foreignId('dai_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->restrictOnDelete();
            $table->string('proficiency', 20);
            $table->primary(['dai_id', 'language_id']);
            $table->index(['language_id', 'proficiency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dai_languages');
    }
};
