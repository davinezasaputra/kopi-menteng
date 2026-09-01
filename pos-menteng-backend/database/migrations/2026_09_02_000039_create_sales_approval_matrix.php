<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_approval_matrix_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('approver_role_id')->constrained('roles')->restrictOnDelete();
            $table->decimal('min_amount',18,2)->default(0);
            $table->decimal('max_amount',18,2)->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id','company_id','branch_id','is_active']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('approval_matrix_rule_id')->nullable()
                ->constrained('sales_approval_matrix_rules')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['approval_matrix_rule_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'approval_matrix_rule_id','approved_by','approved_at',
                'rejected_by','rejected_at','rejection_reason'
            ]);
        });

        Schema::dropIfExists('sales_approval_matrix_rules');
    }
};
