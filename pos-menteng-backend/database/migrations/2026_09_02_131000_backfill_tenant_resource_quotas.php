<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_licenses')) return;

        $defaults = [
            'starter' => [5, 1, 1, 3],
            'business' => [20, 3, 5, 15],
            'professional' => [50, 10, 15, 50],
            'enterprise' => [null, null, null, null],
        ];

        foreach ($defaults as $plan => [$users, $companies, $branches, $locations]) {
            DB::table('tenant_licenses')->where('plan_code', $plan)->update([
                'max_users' => $users,
                'max_companies' => $companies,
                'max_branches' => $branches,
                'max_locations' => $locations,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_licenses')) return;

        DB::table('tenant_licenses')->update([
            'max_companies' => null,
            'max_locations' => null,
            'updated_at' => now(),
        ]);
    }
};
