<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('reservation_number', 80)->unique();
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'company_id', 'branch_id', 'warehouse_id', 'status']);
        });

        Schema::create('inventory_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_reservation_id')->constrained('inventory_reservations')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('fulfilled_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['inventory_reservation_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservation_items');
        Schema::dropIfExists('inventory_reservations');
    }
};