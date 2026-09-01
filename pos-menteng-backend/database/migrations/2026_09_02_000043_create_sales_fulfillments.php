<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_fulfillments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('fulfillment_number',80)->unique();
            $table->string('status',30)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('packed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','sales_order_id']);
            $table->index(['tenant_id','company_id','branch_id','status']);
        });

        Schema::create('sales_fulfillment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_fulfillment_id')->constrained('sales_fulfillments')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->decimal('ordered_quantity',18,4);
            $table->decimal('reserved_quantity',18,4);
            $table->decimal('picked_quantity',18,4)->default(0);
            $table->decimal('packed_quantity',18,4)->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['sales_fulfillment_id','product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_fulfillment_items');
        Schema::dropIfExists('sales_fulfillments');
    }
};
