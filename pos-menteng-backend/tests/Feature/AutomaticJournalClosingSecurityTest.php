<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticJournalClosingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant Auto Journal', 'code' => 'TAJ', 'slug' => 'tenant-auto-journal']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'CAJ', 'name' => 'Company Auto Journal']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'BR-AJ', 'name' => 'Branch Auto Journal']);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Accounting Admin',
            'code' => 'accounting-admin',
            'is_system' => true,
        ]);

        $user = User::factory()->create([
            'default_tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
        ]);

        $membership = Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->app->make(TenantContext::class)->setMembership($membership);
        $this->actingAs($user, 'sanctum');

        return [$tenant, $company, $branch, $user];
    }

    private function accounts(int $tenantId, int $companyId): array
    {
        $debit = ErpAccount::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => '1101',
            'name' => 'Auto Journal Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $credit = ErpAccount::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => '4101',
            'name' => 'Auto Journal Revenue',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        return [$debit, $credit];
    }

    public function test_automatic_source_journal_uses_business_date_and_respects_closing(): void
    {
        [$tenant, $company, $branch] = $this->context();
        [$debit, $credit] = $this->accounts($tenant->id, $company->id);

        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 4,
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'closed',
        ]);

        $service = $this->app->make(ErpAccountingService::class);

        try {
            $service->postSourceJournal(
                'sales_invoice',
                'invoice-closed-1',
                'Backdated sales invoice',
                [
                    ['account_id' => $debit->id, 'debit' => 100000, 'credit' => 0],
                    ['account_id' => $credit->id, 'debit' => 0, 'credit' => 100000],
                ],
                $branch->id,
                '2026-04-15',
            );

            $this->fail('A source journal in a closed fiscal period must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('journal_date', $exception->errors());
        }

        $this->assertDatabaseCount('erp_journal_batches', 0);

        FiscalPeriod::query()
            ->where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('year', 2026)
            ->where('month', 4)
            ->update(['status' => 'open']);

        $batch = $service->postSourceJournal(
            'sales_invoice',
            'invoice-open-1',
            'Open-period sales invoice',
            [
                ['account_id' => $debit->id, 'debit' => 150000, 'credit' => 0],
                ['account_id' => $credit->id, 'debit' => 0, 'credit' => 150000],
            ],
            $branch->id,
            '2026-04-15',
        );

        $this->assertSame('2026-04-15', $batch->journal_date->toDateString());
        $this->assertSame(1, ErpJournalBatch::query()->count());
    }

    public function test_automatic_source_journal_requires_business_date(): void
    {
        [$tenant, $company, $branch] = $this->context();
        [$debit, $credit] = $this->accounts($tenant->id, $company->id);

        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 5,
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $service = $this->app->make(ErpAccountingService::class);

        try {
            $service->postSourceJournal(
                'customer_payment',
                'payment-no-date',
                'Payment without source date',
                [
                    ['account_id' => $debit->id, 'debit' => 50000, 'credit' => 0],
                    ['account_id' => $credit->id, 'debit' => 0, 'credit' => 50000],
                ],
                $branch->id,
            );

            $this->fail('Automatic source journal without a business date must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('journal_date', $exception->errors());
        }

        $this->assertDatabaseCount('erp_journal_batches', 0);
    }
}
