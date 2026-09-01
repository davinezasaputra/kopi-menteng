<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('inventory_balances', 'available_quantity')) {
            return;
        }

        DB::statement(
            'UPDATE inventory_balances
             SET available_quantity = quantity - reserved_quantity'
        );
    }

    public function down(): void
    {
        // Data repair only; no rollback is required.
    }
};
