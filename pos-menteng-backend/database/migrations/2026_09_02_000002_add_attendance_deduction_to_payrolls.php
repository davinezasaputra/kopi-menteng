<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payrolls') && ! Schema::hasColumn('payrolls', 'attendance_deduction')) {
            Schema::table('payrolls', function (Blueprint $table): void {
                $table->decimal('attendance_deduction', 12, 2)->default(0)->after('deduction');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payrolls') && Schema::hasColumn('payrolls', 'attendance_deduction')) {
            Schema::table('payrolls', function (Blueprint $table): void {
                $table->dropColumn('attendance_deduction');
            });
        }
    }
};
