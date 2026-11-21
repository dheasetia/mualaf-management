<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('username', 60)->nullable()->unique()->after('email');
            $table->text('phone_encrypted')->nullable()->after('username');
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('locale', 5)->default('ar')->after('is_active');
            $table->text('app_authentication_secret')->nullable()->after('locale');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        // Backfill existing rows before making uuid required (online-safe pattern, 05 §5.6).
        DB::table('users')->whereNull('uuid')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid7()]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'uuid', 'username', 'phone_encrypted', 'is_active', 'locale',
                'app_authentication_secret', 'app_authentication_recovery_codes',
                'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
