<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_return_id')->nullable()->constrained('supplier_returns')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->string('credit_note_number', 100);
            $table->date('credit_note_date');
            $table->decimal('amount',18,2);
            $table->decimal('applied_amount',18,2)->default(0);
            $table->decimal('remaining_amount',18,2)->default(0);
            $table->string('status',30)->default('open')->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','supplier_id','credit_note_number']);
            $table->index(['tenant_id','company_id','branch_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_notes');
    }
};
