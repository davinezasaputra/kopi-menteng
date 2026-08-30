<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->enum('type', ['cuti_tahunan', 'sakit', 'izin', 'libur_nasional', 'lainnya'])->default('izin');
                $table->date('start_date');
                $table->date('end_date');
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'start_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
