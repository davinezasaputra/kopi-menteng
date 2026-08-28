<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Contoh: Gaji Karyawan, Listrik, Sewa Ruko
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('recorded_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_expenses');
    }
};
