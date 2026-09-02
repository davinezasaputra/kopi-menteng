<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = (int) (DB::table('tenants')->orderBy('id')->value('id') ?? 1);
        $companyId = (int) (DB::table('companies')->where('tenant_id', $tenantId)->orderBy('id')->value('id') ?? 1);
        $branchId = (int) (DB::table('branches')->where('company_id', $companyId)->orderBy('id')->value('id') ?? 1);

        $tables = [
            'employees' => ['tenant_id', 'company_id', 'branch_id'],
            'attendances' => ['tenant_id', 'company_id', 'branch_id'],
            'leaves' => ['tenant_id', 'company_id', 'branch_id'],
            'payrolls' => ['tenant_id', 'company_id', 'branch_id'],
            'operational_expenses' => ['tenant_id', 'company_id', 'branch_id'],
            'customers' => ['tenant_id', 'company_id', 'branch_id'],
            'raw_materials' => ['tenant_id', 'company_id', 'branch_id'],
            'restock_histories' => ['tenant_id', 'company_id', 'branch_id'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $schema) use ($column) {
                        $schema->unsignedBigInteger($column)->nullable()->index();
                    });
                }
            }

            if (Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenantId]);
            }

            if (Schema::hasColumn($table, 'company_id')) {
                DB::table($table)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }

            if (Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }

        // PostgreSQL-safe relationship backfills. Do not rely on Query Builder
        // UPDATE ... JOIN aliases in SET clauses; PostgreSQL rejects those.
        if (Schema::hasTable('attendances') && Schema::hasTable('employees')) {
            DB::statement('
                UPDATE attendances AS a
                SET tenant_id = e.tenant_id,
                    company_id = e.company_id,
                    branch_id = e.branch_id
                FROM employees AS e
                WHERE e.id = a.employee_id
                  AND e.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('leaves') && Schema::hasTable('employees')) {
            DB::statement('
                UPDATE leaves AS l
                SET tenant_id = e.tenant_id,
                    company_id = e.company_id,
                    branch_id = e.branch_id
                FROM employees AS e
                WHERE e.id = l.employee_id
                  AND e.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('payrolls') && Schema::hasTable('employees')) {
            DB::statement('
                UPDATE payrolls AS p
                SET tenant_id = e.tenant_id,
                    company_id = e.company_id,
                    branch_id = e.branch_id
                FROM employees AS e
                WHERE e.id = p.employee_id
                  AND e.tenant_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('restock_histories') && Schema::hasTable('raw_materials')) {
            DB::statement('
                UPDATE restock_histories AS rh
                SET tenant_id = rm.tenant_id,
                    company_id = rm.company_id,
                    branch_id = rm.branch_id
                FROM raw_materials AS rm
                WHERE rm.id = rh.raw_material_id
                  AND rm.tenant_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        // Organization scope is intentionally retained for backward compatibility.
    }
};
