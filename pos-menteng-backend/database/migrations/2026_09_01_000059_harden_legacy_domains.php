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
            'customers' => ['company_id', 'branch_id'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $schema) use ($columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($schema->getTable(), $column)) {
                        $schema->unsignedBigInteger($column)->nullable()->index();
                    }
                }
            });

            $updates = [];
            if (in_array('tenant_id', $columns, true)) $updates['tenant_id'] = $tenantId;
            if (in_array('company_id', $columns, true)) $updates['company_id'] = $companyId;
            if (in_array('branch_id', $columns, true)) $updates['branch_id'] = $branchId;
            if ($updates) DB::table($table)->whereNull('tenant_id')->orWhereNull('company_id')->orWhereNull('branch_id')->update($updates);
        }

        if (Schema::hasTable('attendances') && Schema::hasTable('employees')) {
            DB::table('attendances as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->update([
                'a.tenant_id' => DB::raw('e.tenant_id'),
                'a.company_id' => DB::raw('e.company_id'),
                'a.branch_id' => DB::raw('e.branch_id'),
            ]);
        }
        if (Schema::hasTable('leaves') && Schema::hasTable('employees')) {
            DB::table('leaves as l')->join('employees as e', 'e.id', '=', 'l.employee_id')->update([
                'l.tenant_id' => DB::raw('e.tenant_id'),
                'l.company_id' => DB::raw('e.company_id'),
                'l.branch_id' => DB::raw('e.branch_id'),
            ]);
        }
        if (Schema::hasTable('payrolls') && Schema::hasTable('employees')) {
            DB::table('payrolls as p')->join('employees as e', 'e.id', '=', 'p.employee_id')->update([
                'p.tenant_id' => DB::raw('e.tenant_id'),
                'p.company_id' => DB::raw('e.company_id'),
                'p.branch_id' => DB::raw('e.branch_id'),
            ]);
        }
    }

    public function down(): void
    {
        // Legacy hardening columns are intentionally retained for backward compatibility.
    }
};
