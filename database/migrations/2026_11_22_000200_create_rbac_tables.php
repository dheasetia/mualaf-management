<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('scope_type', 20)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('requires_mfa')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('module', 40)->index();
            $table->string('action', 60);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            // NULL = assigned by the system (bootstrap command or seeder).
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            // One active assignment per user + role + tenant (0 = platform), 05 §5.2.
            $table->string('active_assignment_key', 64)
                ->nullable()
                ->storedAs("IF(revoked_at IS NULL, CONCAT(user_id, ':', role_id, ':', IFNULL(tenant_id, 0)), NULL)");
            $table->unique('active_assignment_key', 'uq_role_assignments_one_active');
            $table->index(['user_id', 'revoked_at']);
            $table->index(['tenant_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
