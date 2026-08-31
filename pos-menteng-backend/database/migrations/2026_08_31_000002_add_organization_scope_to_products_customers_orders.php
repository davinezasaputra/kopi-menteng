<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultTenant = DB::table('tenants')->where('id', 1)->exists()
            ? 1
            : DB::table('tenants')->orderBy('id')->value('id');
        $defaultCompany = DB::table('companies')->where('id', 1)->exists()
            ? 1
            : DB::table('companies')->orderBy('id')->value('id');
        $defaultBranch = DB::table('branches')->where('id', 1)->exists()
            ? 1
            : DB::table('branches')->orderBy('id')->value('id');

        foreach (['products', 'customers', 'orders'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                    $table->index(['tenant_id']);
                });
            }
        }

        if ($defaultTenant) {
            DB::table('products')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenant]);
            DB::table('customers')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenant]);
            DB::table('orders')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenant]);
        }

        if (! Schema::hasColumn('orders', 'company_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('tenant_id')->constrained('companies')->nullOnDelete();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasColumn('orders', 'branch_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
                $table->index(['branch_id']);
            });
        }

        if ($defaultCompany) {
            DB::table('orders')->whereNull('company_id')->update(['company_id' => $defaultCompany]);
        }

        if ($defaultBranch) {
            DB::table('orders')->whereNull('branch_id')->update(['branch_id' => $defaultBranch]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'phone']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'branch_id', 'created_at'], 'orders_org_created_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_tenant_id_is_active_index');
                $table->dropIndex('products_tenant_id_index');
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_tenant_id_phone_index');
                $table->dropIndex('customers_tenant_id_index');
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_org_created_idx');
                $table->dropIndex('orders_branch_id_index');
                $table->dropIndex('orders_company_id_index');
                $table->dropIndex('orders_tenant_id_index');
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['company_id']);
                $table->dropForeign(['tenant_id']);
                $table->dropColumn(['branch_id', 'company_id', 'tenant_id']);
            });
        }
    }
};
