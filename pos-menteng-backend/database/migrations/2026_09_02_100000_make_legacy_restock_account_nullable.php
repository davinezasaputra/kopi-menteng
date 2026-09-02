<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restock_histories') || ! Schema::hasColumn('restock_histories', 'account_id')) {
            return;
        }

        Schema::table('restock_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restock_histories') || ! Schema::hasColumn('restock_histories', 'account_id')) {
            return;
        }

        Schema::table('restock_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
        });
    }
};
