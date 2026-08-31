<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('receipt_number', 80)->unique();
            $table->date('receipt_date');
            $table->string('status', 30)->default('posted')->index();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id','company_id','branch_id','status']);
            $table->index(['tenant_id','company_id','purchase_order_id']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->decimal('ordered_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_value', 18, 2);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['goods_receipt_id','purchase_order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
