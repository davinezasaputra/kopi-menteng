<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 30);
            $table->string('normal_balance', 10);
            $table->foreignId('parent_id')->nullable()->constrained('erp_accounts')->nullOnDelete();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id','company_id','code']);
            $table->index(['tenant_id','company_id','type','is_active']);
        });

        Schema::create('erp_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('journal_number', 80)->unique();
            $table->date('journal_date');
            $table->string('status', 20)->default('posted')->index();
            $table->string('source_type', 50);
            $table->string('source_id', 100)->nullable();
            $table->string('description');
            $table->decimal('total_debit',18,2)->default(0);
            $table->decimal('total_credit',18,2)->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id','company_id','journal_date']);
        });

        Schema::create('erp_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_batch_id')->constrained('erp_journal_batches')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('erp_accounts')->restrictOnDelete();
            $table->decimal('debit',18,2)->default(0);
            $table->decimal('credit',18,2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_journal_lines');
        Schema::dropIfExists('erp_journal_batches');
        Schema::dropIfExists('erp_accounts');
    }
};
