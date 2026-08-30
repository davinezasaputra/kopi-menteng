<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Modify status to include leave types
            // Status options: hadir, terlambat, izin, sakit, cuti_tahunan, libur_nasional, lainnya
            $table->string('status')->change(); // Remove default constraint for modification
            
            // Add leave_id for tracking which leave record this is
            if (!Schema::hasColumn('attendances', 'leave_id')) {
                $table->foreignId('leave_id')->nullable()->constrained('leaves')->cascadeOnDelete();
            }

            // Add notes for additional information
            if (!Schema::hasColumn('attendances', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'leave_id')) {
                $table->dropForeignKeyIfExists(['leave_id']);
                $table->dropColumn('leave_id');
            }
            if (Schema::hasColumn('attendances', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
