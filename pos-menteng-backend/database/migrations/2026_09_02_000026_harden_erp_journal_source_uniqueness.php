<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_journal_batches', function (Blueprint $table) {
            $table->unique(
                ['tenant_id','company_id','source_type','source_id'],
                'erp_journal_batches_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('erp_journal_batches', function (Blueprint $table) {
            $table->dropUnique('erp_journal_batches_source_unique');
        });
    }
};
