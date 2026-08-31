<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_balances', 'last_cost')) {
                $table->decimal('last_cost', 18, 4)->default(0)->after('average_cost');
            }

            if (! Schema::hasColumn('inventory_balances', 'available_quantity')) {
                $table->decimal('available_quantity', 18, 4)->default(0)->after('reserved_quantity');
            }
        });

        DB::statement('
            UPDATE inventory_balances
            SET last_cost = COALESCE(average_cost, 0),
                available_quantity = COALESCE(quantity, 0) - COALESCE(reserved_quantity, 0)
        ');
    }

    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_balances', 'available_quantity')) {
                $table->dropColumn('available_quantity');
            }

            if (Schema::hasColumn('inventory_balances', 'last_cost')) {
                $table->dropColumn('last_cost');
            }
        });
    }
};
