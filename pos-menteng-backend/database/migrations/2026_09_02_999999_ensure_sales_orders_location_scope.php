<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexName(): string
    {
        return 'org_location_scope_sales_orders_idx';
    }

    public function up(): void
    {
        if (! Schema::hasTable('sales_orders') || Schema::hasColumn('sales_orders', 'location_id')) {
            return;
        }

        $indexName = $this->indexName();

        Schema::table('sales_orders', function (Blueprint $table) use ($indexName): void {
            $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
            $table->index(['tenant_id', 'company_id', 'branch_id', 'location_id'], $indexName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_orders') || ! Schema::hasColumn('sales_orders', 'location_id')) {
            return;
        }

        $indexName = $this->indexName();

        Schema::table('sales_orders', function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
