<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyAccountingFacadeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(bool $withJournalPermission = true): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Legacy Accounting',
            'code' => 'TLGA',
            'slug' => 'tenant-legacy-accounting',
        ]);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'CLGA',
            'name' => 'Company Legacy Accounting',
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'BR-LGA',
            'name' => 'Branch Legacy Accounting',
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Accounting Test',
            'code' => 'accounting-test',
            'is_system' => false,
        ]);

        $permissionNames = ['accounting.erp_account.view'];
        if ($withJournalPermission) {
            $permissionNames[] = 'accounting.erp_journal.create';
            $permissionNames[] = 'accounting.erp_journal.view';
        }

        foreach ($permissionNames as $name) {
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

        Sanctum::actingAs($user);

        return [$tenant, $company, $branch, $user];
    }

    private function accounts(int $tenantId, int $companyId): array
    {
        $cash = ErpAccount::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);
        $expense = ErpAccount::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => '5100',
            'name' => 'Operational Expense',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        return [$cash, $expense];
    }

    private function openPeriod(Tenant $tenant, Company $company): void
    {
        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 9,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);
    }

    public function test_legacy_journal_endpoint_now_posts_balanced_erp_journal_only(): void
    {
        [$tenant, $company, $branch] = $this->identity();
        [$cash, $expense] = $this->accounts($tenant->id, $company->id);
        $this->openPeriod($tenant, $company);

        $response = $this->postJson('/api/accounting/journals', [
            'branch_id' => $branch->id,
            'journal_date' => '2026-09-15',
            'description' => 'Manual electricity payment',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 250000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 250000],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('erp_journal_batches', 1);
        $batch = ErpJournalBatch::query()->firstOrFail();
        $this->assertSame('manual', $batch->source_type);
        $this->assertSame('2026-09-15', $batch->journal_date->toDateString());
        $this->assertSame($branch->id, $batch->branch_id);
        $this->assertSame(250000.0, (float) $batch->total_debit);
        $this->assertSame(250000.0, (float) $batch->total_credit);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_legacy_journal_endpoint_respects_closed_period(): void
    {
        [$tenant, $company, $branch] = $this->identity();
        [$cash, $expense] = $this->accounts($tenant->id, $company->id);
        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 9,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'closed',
        ]);

        $response = $this->postJson('/api/accounting/journals', [
            'branch_id' => $branch->id,
            'journal_date' => '2026-09-15',
            'description' => 'Closed period attempt',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('erp_journal_batches', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_legacy_journal_endpoint_rejects_cross_company_erp_account(): void
    {
        [$tenant, $company, $branch] = $this->identity();
        [$cash] = $this->accounts($tenant->id, $company->id);
        $this->openPeriod($tenant, $company);

        $otherCompany = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'CLGA-2',
            'name' => 'Other Company',
        ]);
        $otherAccount = ErpAccount::create([
            'tenant_id' => $tenant->id,
            'company_id' => $otherCompany->id,
            'code' => '5100',
            'name' => 'Other Expense',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->postJson('/api/accounting/journals', [
            'branch_id' => $branch->id,
            'journal_date' => '2026-09-15',
            'description' => 'Cross company attempt',
            'lines' => [
                ['account_id' => $otherAccount->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100000],
            ],
        ])->assertNotFound();

        $this->assertDatabaseCount('erp_journal_batches', 0);
    }

    public function test_legacy_journal_endpoint_requires_erp_journal_permission(): void
    {
        [$tenant, $company, $branch] = $this->identity(false);
        [$cash, $expense] = $this->accounts($tenant->id, $company->id);
        $this->openPeriod($tenant, $company);

        $this->postJson('/api/accounting/journals', [
            'branch_id' => $branch->id,
            'journal_date' => '2026-09-15',
            'description' => 'Permission attempt',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100000],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('erp_journal_batches', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
