<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchasing_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedSmallInteger('budget_year');
            $table->decimal('allocated_amount',18,2)->default(0);
            $table->decimal('committed_amount',18,2)->default(0);
            $table->decimal('spent_amount',18,2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','company_id','branch_id','budget_year']);
            $table->index(['tenant_id','company_id','branch_id','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_budgets');
    }
};
