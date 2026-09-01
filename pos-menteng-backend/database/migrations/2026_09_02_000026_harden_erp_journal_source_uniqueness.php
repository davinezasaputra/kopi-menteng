<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the earliest journal for each business source and remove
        // duplicate test/retry rows that existed before this constraint.
        DB::statement(<<<'SQL'
            DELETE FROM erp_journal_batches duplicate
            USING erp_journal_batches keeper
            WHERE duplicate.id > keeper.id
              AND duplicate.tenant_id = keeper.tenant_id
              AND duplicate.company_id = keeper.company_id
              AND duplicate.source_type = keeper.source_type
              AND duplicate.source_id = keeper.source_id
              AND duplicate.source_id IS NOT NULL
        SQL);

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
