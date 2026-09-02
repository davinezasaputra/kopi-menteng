<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shifts', 'location_id')) {
            Schema::table('shifts', function (Blueprint $table): void {
                $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
                $table->index(['tenant_id', 'company_id', 'branch_id', 'location_id', 'user_id', 'status'], 'shifts_location_scope_idx');
            });
        }

        if (! Schema::hasColumn('orders', 'location_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
                $table->index(['tenant_id', 'company_id', 'branch_id', 'location_id', 'created_at'], 'orders_location_scope_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts', 'location_id')) {
            Schema::table('shifts', function (Blueprint $table): void {
                $table->dropIndex('shifts_location_scope_idx');
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        if (Schema::hasColumn('orders', 'location_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex('orders_location_scope_idx');
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }
    }
};
