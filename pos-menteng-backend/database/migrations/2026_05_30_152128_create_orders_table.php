<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignUuid('shift_id')->constrained('shifts');
            $table->string('invoice_number');
            $table->decimal('subtotal',15 ,2)->default(0);
            $table->decimal('tax',15 ,2)->default(0);
            $table->decimal('discount',15 ,2)->default(0);
            $table->decimal('total',15 ,2)->default(0);
            $table->enum('payment_method', ['cash', 'qris', 'bank_transfer', 'unpaid'])->default('unpaid');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
