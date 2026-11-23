<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 13 §13.6: browsers an account has signed in from, to warn about new ones.
 * Only a keyed hash of the user agent is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_devices');
    }
};
