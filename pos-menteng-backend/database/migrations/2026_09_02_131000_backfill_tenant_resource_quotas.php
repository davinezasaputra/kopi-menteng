<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_licenses')) return;

        $defaults = [
            'starter' => [1, 3],
            'business' => [3, 15],
            'professional' => [10, 50],
            'enterprise' => [null, null],
        ];

        foreach ($defaults as $plan => [$companies, $locations]) {
            DB::table('tenant_licenses')
                ->where('plan_code', $plan)
                ->where(function ($query): void {
                    $query->whereNull('max_companies')->orWhereNull('max_locations');
                })
                ->update([
                    'max_companies' => $companies,
                    'max_locations' => $locations,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep existing tenant quota configuration intact on rollback.
    }
};
