<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 08-data-model-dawah §8.3 (budget) and §8.4 (materials).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dawah_activity_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_plan_id')->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->foreignId('budget_category_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('estimated_amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->decimal('actual_amount', 14, 2)->nullable();
            $table->decimal('variance_amount', 14, 2)->storedAs('IFNULL(actual_amount, 0) - IFNULL(approved_amount, estimated_amount)');
            $table->unsignedBigInteger('receipt_document_id')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index('activity_plan_id');
        });

        Schema::create('dawah_materials', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('material_type', 20);
            $table->foreignId('language_id')->constrained()->restrictOnDelete();
            $table->string('topic')->nullable();
            $table->string('target_audience')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('file_document_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('version', 20);
            $table->foreignId('parent_material_id')->nullable()->constrained('dawah_materials')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['language_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('dawah_material_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_plan_id')->nullable()->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->foreignId('dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('dawah_materials')->restrictOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('distributed_at');
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['material_id', 'distributed_at']);
        });
    }

    public function down(): void
    {
        foreach (['dawah_material_distributions', 'dawah_materials', 'dawah_activity_budgets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
