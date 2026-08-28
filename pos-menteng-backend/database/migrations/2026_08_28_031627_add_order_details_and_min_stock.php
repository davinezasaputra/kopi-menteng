<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // 1. Tambah Tipe Pesanan & Nama/Meja di Transaksi
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type')->default('dine_in')->after('status');
            $table->string('customer_name')->nullable()->after('order_type');
        });

        // 2. Tambah Batas Aman Stok di Bahan Baku
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->decimal('min_stock_level', 10, 2)->default(0)->after('stock');
        });
    }
};