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
        Schema::create('restock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('raw_material_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_added');
            $table->decimal('total_cost', 12, 2);
            $table->string('receipt_image')->nullable(); // Opsional untuk upload struk
            $table->string('restocked_by'); // Nama Manager/Owner
            $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restock_histories');
    }
};
