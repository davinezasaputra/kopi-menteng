<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->foreignId('default_company_id')->nullable()->after('default_tenant_id')->constrained('companies')->nullOnDelete();
            $table->foreignId('default_branch_id')->nullable()->after('default_company_id')->constrained('branches')->nullOnDelete();
            $table->index(['default_tenant_id', 'default_company_id', 'default_branch_id'], 'users_erp_context_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_erp_context_index');
            $table->dropConstrainedForeignId('default_branch_id');
            $table->dropConstrainedForeignId('default_company_id');
            $table->dropConstrainedForeignId('default_tenant_id');
        });
    }
};
