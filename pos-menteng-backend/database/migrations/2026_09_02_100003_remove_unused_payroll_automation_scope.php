<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_automation_config')) return;
        Schema::table('payroll_automation_config', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_automation_config', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('payroll_automation_config', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_automation_config')) return;
        Schema::table('payroll_automation_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_automation_config', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('tenant_id')->constrained('companies')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('payroll_automation_config', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->cascadeOnDelete();
            }
        });
    }
};
