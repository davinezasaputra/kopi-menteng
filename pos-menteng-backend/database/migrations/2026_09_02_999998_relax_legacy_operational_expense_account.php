<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_expenses') || ! Schema::hasColumn('operational_expenses', 'account_id')) {
            return;
        }

        Schema::table('operational_expenses', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operational_expenses') || ! Schema::hasColumn('operational_expenses', 'account_id')) {
            return;
        }

        Schema::table('operational_expenses', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable(false)->change();
        });
    }
};
