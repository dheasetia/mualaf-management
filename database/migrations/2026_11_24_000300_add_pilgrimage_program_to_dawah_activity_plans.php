<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10 §10.2: transport for a pilgrimage program goes through an activity
 * (departure, return) linked to the program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dawah_activity_plans', function (Blueprint $table): void {
            $table->foreignId('pilgrimage_program_id')->nullable()->after('activity_type_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dawah_activity_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pilgrimage_program_id');
        });
    }
};
