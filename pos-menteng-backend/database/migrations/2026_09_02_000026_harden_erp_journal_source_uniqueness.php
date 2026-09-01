<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS erp_journal_batches_source_unique
            ON erp_journal_batches (tenant_id, company_id, source_type, source_id)
            WHERE source_type IN (
                'purchase_order',
                'goods_receipt',
                'supplier_invoice',
                'supplier_payment'
            )
              AND source_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS erp_journal_batches_source_unique'
        );
    }
};
