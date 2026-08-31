<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->unique(['tenant_id','request_id'],'supplier_invoices_tenant_request_unique');
            $table->unique(['tenant_id','goods_receipt_id'],'supplier_invoices_tenant_receipt_unique');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->unique(['tenant_id','request_id'],'supplier_payments_tenant_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropUnique('supplier_payments_tenant_request_unique');
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropUnique('supplier_invoices_tenant_receipt_unique');
            $table->dropUnique('supplier_invoices_tenant_request_unique');
        });
    }
};
