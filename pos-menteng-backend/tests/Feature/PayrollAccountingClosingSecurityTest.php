<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Hrm\Services\PayrollAccountingService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAccountingClosingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant Payroll Accounting', 'code' => 'TPA', 'slug' => 'tenant-payroll-accounting']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'CPA', 'name' => 'Company Payroll Accounting']);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'BR-PA', 'name' => 'Branch Payroll Accounting']);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Payroll Admin',
            'code' => 'payroll-accounting-admin',
            'is_system' => false,
        ]);

        foreach (['hr.employee.view', 'hr.employee.manage'] as $name) {
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

    private function accounts(int $tenantId, int $companyId): array
    {
        $expense = ErpAccount::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => '5200',
            'name' => 'Salary Expense',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $cash = ErpAccount::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => '1000',
            'name' => 'Cash Payroll',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        return [$expense, $cash];
    }

    private function payroll(Tenant $tenant, Company $company, Branch $branch, string $period = '2026-09'): Payroll
    {
        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Payroll Accounting Employee',
            'WA' => '081234567890',
            'position' => 'Barista',
            'base_sallary' => 5000000,
            'status' => 'active',
        ]);

        return Payroll::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'period' => $period,
            'base_salary' => 5000000,
            'allowance' => 0,
            'deduction' => 0,
            'attendance_deduction' => 0,
            'total_salary' => 5000000,
            'is_paid' => false,
        ]);
    }

    public function test_payroll_payment_is_blocked_when_payroll_period_is_closed(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $payroll = $this->payroll($tenant, $company, $branch, '2026-09');

        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 9,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/hrm/payrolls/' . $payroll->id . '/pay');

        $response->assertUnprocessable()->assertJsonValidationErrors(['journal_date']);
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'is_paid' => false]);
        $this->assertDatabaseCount('erp_journal_batches', 0);
    }

    public function test_paid_payroll_posts_erp_journal_on_period_business_date(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $payroll = $this->payroll($tenant, $company, $branch, '2026-09');

        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 9,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/hrm/payrolls/' . $payroll->id . '/pay');

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'is_paid' => true]);
        $this->assertDatabaseCount('erp_journal_batches', 1);

        $batch = ErpJournalBatch::query()->firstOrFail();
        $this->assertSame('2026-09-30', $batch->journal_date->toDateString());
        $this->assertSame('payroll_payment', $batch->source_type);
        $this->assertSame((string) $payroll->id, (string) $batch->source_id);
        $this->assertSame($branch->id, $batch->branch_id);
    }

    public function test_payroll_accounting_service_rejects_cross_company_payroll(): void
    {
        [$tenant, $company, $branch] = $this->identity();
        $this->accounts($tenant->id, $company->id);

        $otherCompany = Company::create(['tenant_id' => $tenant->id, 'code' => 'CPA-2', 'name' => 'Other Payroll Company']);
        $otherBranch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $otherCompany->id, 'code' => 'BR-PA-2', 'name' => 'Other Payroll Branch']);
        $payroll = $this->payroll($tenant, $otherCompany, $otherBranch, '2026-09');

        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->firstOrFail();

        $this->app->make(\App\Support\Tenancy\TenantContext::class)->setMembership($membership);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->app->make(PayrollAccountingService::class)->postPayment($payroll->forceFill(['is_paid' => true]));
    }
}
