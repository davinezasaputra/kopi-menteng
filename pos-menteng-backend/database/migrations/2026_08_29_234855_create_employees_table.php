<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->date('tanggal_lahir')->nullable();
                $table->string('WA');
                $table->string('position');
                $table->date('join_date')->nullable();
                $table->decimal('base_sallary', 12, 2)->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
