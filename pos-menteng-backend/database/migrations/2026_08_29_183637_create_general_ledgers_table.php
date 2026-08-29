<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('description');
            $table->string('account_category');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            
            // Relasi Morph untuk mendeteksi sumber transaksi otomatis
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('general_ledgers');
    }
};
