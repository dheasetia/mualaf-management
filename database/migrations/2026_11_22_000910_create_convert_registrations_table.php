<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convert_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->unique()->constrained()->restrictOnDelete();
            $table->string('registration_no', 30);
            $table->date('registration_date');
            $table->string('status', 40);
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'registration_no']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('convert_registrations is never dropped in production.');
        }

        Schema::dropIfExists('convert_registrations');
    }
};
