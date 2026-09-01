<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('memberships', 'location_id')) {
            Schema::table('memberships', function (Blueprint $table): void {
                $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
                $table->index(['tenant_id', 'company_id', 'branch_id', 'location_id'], 'memberships_org_location_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('memberships', 'location_id')) return;

        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropIndex('memberships_org_location_idx');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
