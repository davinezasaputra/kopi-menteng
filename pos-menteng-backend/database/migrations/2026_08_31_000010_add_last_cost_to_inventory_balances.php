<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_balances', 'last_cost')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->decimal('last_cost', 18, 4)->default(0)->after('average_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_balances', 'last_cost')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->dropColumn('last_cost');
            });
        }
    }
};
