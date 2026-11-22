<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convert_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->string('contact_type', 20);
            $table->text('value_encrypted');
            $table->binary('value_fingerprint', 32, fixed: true);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('value_fingerprint');
            $table->index(['convert_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convert_contacts');
    }
};
