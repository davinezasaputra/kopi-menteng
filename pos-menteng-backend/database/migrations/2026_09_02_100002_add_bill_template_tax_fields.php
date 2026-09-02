<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receipt_templates')) {
            return;
        }

        Schema::table('receipt_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('receipt_templates', 'bill_title')) {
                $table->string('bill_title', 120)->default('NOTA PENJUALAN');
            }
            if (! Schema::hasColumn('receipt_templates', 'bill_subtitle')) {
                $table->string('bill_subtitle', 255)->nullable();
            }
            if (! Schema::hasColumn('receipt_templates', 'ppn_rate')) {
                $table->decimal('ppn_rate', 5, 2)->default(11);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('receipt_templates')) return;
        Schema::table('receipt_templates', function (Blueprint $table): void {
            foreach (['bill_title', 'bill_subtitle', 'ppn_rate'] as $column) {
                if (Schema::hasColumn('receipt_templates', $column)) $table->dropColumn($column);
            }
        });
    }
};
