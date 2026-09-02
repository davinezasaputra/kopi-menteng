<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Services\OperationalExpenseAccountingService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\JournalEntry;
use App\Models\OperationalExpense;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalExpenseAccountingClosingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Operational Expense',
            'code' => 'TOPEX',
            'slug' => 'tenant-operational-expense',
        ]);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'COPEX',
            'name' => 'Company Operational Expense',
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'BR-OPEX',
            'name' => 'Branch Operational Expense',
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Finance Admin',
            'code' => 'finance-admin-test',
            'is_system' => false,
        ]);

        foreach (['accounting.report.view', 'accounting.journal.create'] as $name) {
            [$module, $resource, $action] = explode('.', $name);
            $permission = Permission::firstOrCreate([
                'module' => $module,
                'resource' => $resource,
                'action' => $action,
                'name' => $name,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create([
            'default_tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
        ]);

        $user->memberships()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        return [$tenant, $company, $branch, $user];
    }

    private function accounts(int $tenantId, int $companyId): void
    {
        foreach ([
            ['1000', 'Cash', 'asset', 'debit'],
            ['5100', 'Operational Expense', 'expense', 'debit'],
        ] as [$code, $name, $type, $normalBalance]) {
            ErpAccount::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'normal_balance' => $normalBalance,
                'is_postable' => true,
                'is_active' => true,
            ]);
        }
    }

    private function expense(Tenant $tenant, Company $company, Branch $branch, User $user): OperationalExpense
    {
        return OperationalExpense::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Listrik Kedai',
            'amount' => 750000,
            'expense_date' => '2026-09-15',
            'recorded_by' => $user->name,
        ]);
    }

    private function setContext(Tenant $tenant, Company $company, Branch $branch): void
    {
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)->setMembership($membership);
    }

    private function period(Tenant $tenant, Company $company, string $status): void
    {
        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 9,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => $status,
        ]);
    }

    public function test_expense_posts_erp_journal_on_expense_business_date(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $expense = $this->expense($tenant, $company, $branch, $user);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);

        $this->app->make(OperationalExpenseAccountingService::class)->postExpense($expense);

        $this->assertDatabaseCount('erp_journal_batches', 1);
        $batch = ErpJournalBatch::query()->firstOrFail();
        $this->assertSame('2026-09-15', $batch->journal_date->toDateString());
        $this->assertSame('operational_expense', $batch->source_type);
        $this->assertSame((string) $expense->id, (string) $batch->source_id);
        $this->assertSame($branch->id, $batch->branch_id);
        $this->assertSame($user->id, $batch->created_by);
        $this->assertSame(750000.0, (float) $batch->total_debit);
        $this->assertSame(750000.0, (float) $batch->total_credit);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_expense_accounting_is_blocked_by_closed_period(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $expense = $this->expense($tenant, $company, $branch, $user);
        $this->period($tenant, $company, 'closed');
        $this->setContext($tenant, $company, $branch);

        $this->expectException(ValidationException::class);
        try {
            $this->app->make(OperationalExpenseAccountingService::class)->postExpense($expense);
        } finally {
            $this->assertDatabaseCount('erp_journal_batches', 0);
        }
    }

    public function test_duplicate_expense_accounting_is_idempotent(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $expense = $this->expense($tenant, $company, $branch, $user);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);

        $service = $this->app->make(OperationalExpenseAccountingService::class);
        $service->postExpense($expense);
        $service->postExpense($expense->fresh());

        $this->assertDatabaseCount('erp_journal_batches', 1);
    }

    public function test_cross_company_expense_is_rejected(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);

        $otherCompany = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'COPEX-2',
            'name' => 'Other Operational Expense Company',
        ]);
        $otherBranch = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $otherCompany->id,
            'code' => 'BR-OPEX-2',
            'name' => 'Other Operational Expense Branch',
        ]);
        $expense = OperationalExpense::create([
            'tenant_id' => $tenant->id,
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Cross Company Expense',
            'amount' => 100000,
            'expense_date' => Carbon::parse('2026-09-15'),
            'recorded_by' => $user->name,
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(OperationalExpenseAccountingService::class)->postExpense($expense);
    }
}
