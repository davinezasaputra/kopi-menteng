<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignUuid('sales_shipment_id')->constrained('sales_shipments')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('invoice_number',80)->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal',18,2)->default(0);
            $table->decimal('discount_amount',18,2)->default(0);
            $table->decimal('tax_amount',18,2)->default(0);
            $table->decimal('total_amount',18,2)->default(0);
            $table->decimal('paid_amount',18,2)->default(0);
            $table->decimal('outstanding_amount',18,2)->default(0);
            $table->string('status',30)->default('unpaid')->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','sales_shipment_id']);
            $table->index(['tenant_id','company_id','branch_id','status']);
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->decimal('quantity',18,4);
            $table->decimal('unit_price',18,4);
            $table->decimal('discount_amount',18,2)->default(0);
            $table->decimal('tax_amount',18,2)->default(0);
            $table->decimal('line_total',18,2);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['sales_invoice_id','product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
    }
};
