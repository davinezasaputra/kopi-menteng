<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignUuid('sales_fulfillment_id')->constrained('sales_fulfillments')->cascadeOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->constrained('inventory_reservations')->nullOnDelete();
            $table->string('shipment_number',80)->unique();
            $table->date('shipment_date');
            $table->string('status',30)->default('shipped')->index();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->foreignId('shipped_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('shipped_at');
            $table->uuid('request_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','sales_fulfillment_id']);
            $table->index(['tenant_id','company_id','branch_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_shipments');
    }
};
