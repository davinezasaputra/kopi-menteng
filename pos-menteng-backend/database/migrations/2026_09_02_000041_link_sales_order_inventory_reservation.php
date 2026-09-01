<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_reservations', 'sales_order_id')) {
            Schema::table('inventory_reservations', function (Blueprint $table) {
                $table->uuid('sales_order_id')->nullable()->after('reference_id');
                $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
                $table->index(['tenant_id','company_id','branch_id','sales_order_id']);
            });
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS inventory_reservations_sales_order_unique
            ON inventory_reservations (tenant_id, company_id, sales_order_id)
            WHERE sales_order_id IS NOT NULL
        SQL);

        if (! Schema::hasColumn('sales_orders', 'inventory_reservation_id')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->foreignId('inventory_reservation_id')->nullable()
                    ->constrained('inventory_reservations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_reservations_sales_order_unique');

        if (Schema::hasColumn('sales_orders', 'inventory_reservation_id')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropForeign(['inventory_reservation_id']);
                $table->dropColumn('inventory_reservation_id');
            });
        }

        if (Schema::hasColumn('inventory_reservations', 'sales_order_id')) {
            Schema::table('inventory_reservations', function (Blueprint $table) {
                $table->dropForeign(['sales_order_id']);
                $table->dropIndex(['tenant_id','company_id','branch_id','sales_order_id']);
                $table->dropColumn('sales_order_id');
            });
        }
    }
};
