<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shifts', 'tenant_id')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained('tenants')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->after('tenant_id')->constrained('companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
                $table->index(['tenant_id', 'company_id', 'branch_id', 'user_id', 'status'], 'shifts_org_user_status_idx');
            });
        }

        $defaultTenant = DB::table('tenants')->where('id', 1)->exists() ? 1 : DB::table('tenants')->orderBy('id')->value('id');
        $defaultCompany = DB::table('companies')->where('id', 1)->exists() ? 1 : DB::table('companies')->orderBy('id')->value('id');
        $defaultBranch = DB::table('branches')->where('id', 1)->exists() ? 1 : DB::table('branches')->orderBy('id')->value('id');
        $defaultWarehouse = DB::table('warehouses')->where('id', 1)->exists() ? 1 : DB::table('warehouses')->orderBy('id')->value('id');

        if ($defaultTenant) DB::table('shifts')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenant]);
        if ($defaultCompany) DB::table('shifts')->whereNull('company_id')->update(['company_id' => $defaultCompany]);
        if ($defaultBranch) DB::table('shifts')->whereNull('branch_id')->update(['branch_id' => $defaultBranch]);
        if ($defaultWarehouse) DB::table('shifts')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouse]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('shifts')) return;

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex('shifts_org_user_status_idx');
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['company_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['warehouse_id', 'branch_id', 'company_id', 'tenant_id']);
        });
    }
};
