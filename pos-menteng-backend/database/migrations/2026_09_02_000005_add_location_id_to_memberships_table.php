<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
            $table->index(['tenant_id', 'company_id', 'branch_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['tenant_id', 'company_id', 'branch_id', 'location_id']);
            $table->dropColumn('location_id');
        });
    }
};
