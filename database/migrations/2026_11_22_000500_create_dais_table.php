<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dais', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('employee_number', 30);
            $table->string('full_name');
            $table->string('full_name_ar')->nullable();
            $table->string('gender', 10);
            $table->foreignId('nationality_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->text('phone_encrypted')->nullable();
            $table->string('email')->nullable();
            $table->string('education')->nullable();
            $table->json('specialties');
            $table->foreignId('supervisor_dai_id')->nullable()->constrained('dais')->restrictOnDelete();
            $table->date('joined_at')->nullable();
            $table->string('employment_status', 40);
            $table->string('status', 40)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_number']);
            // A user account is linked to at most one da'i record per office.
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dais');
    }
};
