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
        // Tambah Harga Beli Satuan di Bahan Baku
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->decimal('price_per_unit', 10, 2)->default(0)->after('stock');
    });

    // Tambah HPP (Modal) di Transaksi
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_cogs', 12, 2)->default(0)->after('total_price');
            $table->decimal('net_profit', 12, 2)->default(0)->after('total_cogs');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders_and_materials', function (Blueprint $table) {
            //
        });
    }
};
