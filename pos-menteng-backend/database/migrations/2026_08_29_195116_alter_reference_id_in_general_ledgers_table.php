<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Hapus kolom integer lama yang bermasalah
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->dropColumn(['reference_type', 'reference_id']);
        });

        // 2. Tambahkan kolom baru dengan dukungan penuh UUID
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->nullableUuidMorphs('reference');
        });
    }

    public function down()
    {
        // Rollback fallback jika dibutuhkan
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->dropColumn(['reference_type', 'reference_id']);
        });
        
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
        });
    }
};