<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Seeder;

class OperationalExpenseAccountSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'DEMO')->first();
        if (! $tenant) {
            return;
        }

        $company = Company::query()->where('tenant_id', $tenant->id)->where('code', 'KM')->first();
        if (! $company) {
            return;
        }

        ErpAccount::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'code' => '5100',
            ],
            [
                'name' => 'Operational Expense',
                'type' => 'expense',
                'normal_balance' => 'debit',
                'is_postable' => true,
                'is_active' => true,
            ]
        );
    }
}
