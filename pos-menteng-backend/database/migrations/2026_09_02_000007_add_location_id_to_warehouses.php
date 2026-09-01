<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouses', 'location_id')) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
                $table->index(['branch_id', 'location_id', 'status'], 'warehouses_location_scope_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('warehouses', 'location_id')) return;

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropIndex('warehouses_location_scope_idx');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
