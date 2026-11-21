<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tenant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('active');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // MySQL has no partial unique index: only active rows produce a key, so
            // at most one active membership per user + tenant (05-database-conventions §5.2).
            $table->string('active_membership_key', 41)
                ->nullable()
                ->storedAs("IF(status = 'active', CONCAT(user_id, ':', tenant_id), NULL)");
            $table->unique('active_membership_key', 'uq_memberships_one_active');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tenant_memberships');
    }
};
