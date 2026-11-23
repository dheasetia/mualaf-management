<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 13 §13.1: uploads are scanned before they can be verified or downloaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('scan_status', 20)->default('skipped')->after('verification_status');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['scan_status', 'scanned_at']);
        });
    }
};
