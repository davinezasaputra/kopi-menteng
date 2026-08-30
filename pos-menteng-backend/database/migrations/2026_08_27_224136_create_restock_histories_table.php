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
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('restock_histories')) {
            Schema::create('restock_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignUuid('raw_material_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity_added');
                $table->decimal('total_cost', 12, 2);
                $table->string('receipt_image')->nullable();
                $table->string('restocked_by');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restock_histories');
    }
};
