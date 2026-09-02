<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_settings')) {
            Schema::create('attendance_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->time('clock_in_time')->default('08:00:00');
                $table->unsignedSmallInteger('clock_in_grace_minutes')->default(15);
                $table->time('clock_out_time')->default('17:00:00');
                $table->unsignedSmallInteger('clock_out_grace_minutes')->default(0);
                $table->boolean('auto_absence_enabled')->default(false);
                $table->timestamps();
                $table->unique(['tenant_id', 'company_id', 'branch_id'], 'attendance_settings_scope_unique');
            });
        }

        if (! Schema::hasTable('attendance_penalties')) {
            Schema::create('attendance_penalties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->enum('penalty_type', ['late', 'absence']);
                $table->string('duration_threshold', 20)->default('0');
                $table->enum('amount_type', ['fixed', 'percentage'])->default('fixed');
                $table->decimal('amount', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['tenant_id', 'company_id', 'branch_id', 'penalty_type', 'is_active'], 'attendance_penalties_scope_index');
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table): void {
                if (! Schema::hasColumn('attendances', 'early_leave_minute')) {
                    $table->unsignedInteger('early_leave_minute')->default(0)->after('late_minute');
                }
                if (Schema::hasColumn('attendances', 'clock_in')) {
                    $table->dateTime('clock_in')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('payroll_automation_config')) {
            Schema::table('payroll_automation_config', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_automation_config', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('tenant_id')->constrained('companies')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('payroll_automation_config', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_automation_config')) {
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

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table): void {
                if (Schema::hasColumn('attendances', 'early_leave_minute')) {
                    $table->dropColumn('early_leave_minute');
                }
            });
        }

        Schema::dropIfExists('attendance_penalties');
        Schema::dropIfExists('attendance_settings');
    }
};
