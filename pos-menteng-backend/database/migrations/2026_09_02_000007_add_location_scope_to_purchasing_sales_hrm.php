<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            // Purchasing operational documents.
            'purchase_requisitions',
            'purchase_orders',
            'goods_receipts',
            'supplier_invoices',
            'supplier_payments',
            'supplier_returns',
            'supplier_credit_notes',
            // Sales operational documents.
            'sales_orders',
            'sales_fulfillments',
            'sales_shipments',
            'sales_invoices',
            'customer_payments',
            'sales_returns',
            // HR snapshots.
            'employees',
            'attendances',
            'payrolls',
            'leaves',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'location_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
                $table->index(['tenant_id', 'company_id', 'branch_id', 'location_id'], 'org_location_scope_idx');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'purchase_requisitions',
            'purchase_orders',
            'goods_receipts',
            'supplier_invoices',
            'supplier_payments',
            'supplier_returns',
            'supplier_credit_notes',
            'sales_orders',
            'sales_fulfillments',
            'sales_shipments',
            'sales_invoices',
            'customer_payments',
            'sales_returns',
            'employees',
            'attendances',
            'payrolls',
            'leaves',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'location_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex('org_location_scope_idx');
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }
    }
};
