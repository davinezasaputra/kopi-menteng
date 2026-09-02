<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table): void {
            $table->unsignedInteger('max_companies')->nullable()->after('max_users');
            $table->unsignedInteger('max_locations')->nullable()->after('max_branches');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table): void {
            $table->dropColumn(['max_companies', 'max_locations']);
        });
    }
};
