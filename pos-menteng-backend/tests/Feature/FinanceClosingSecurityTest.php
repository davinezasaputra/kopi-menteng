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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceClosingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(): array
    {
        $tenant = Tenant::create(['name'=>'Tenant Finance','code'=>'TF','slug'=>'tenant-finance']);
        $company = Company::create(['tenant_id'=>$tenant->id,'code'=>'CF','name'=>'Company Finance']);
        $branch = Branch::create(['company_id'=>$company->id,'code'=>'BR-F','name'=>'Branch Finance']);

        $permissions = [
            'accounting.fiscal_period.manage',
            'accounting.period.close',
            'accounting.erp_journal.create',
        ];

        $role = Role::create([
            'tenant_id'=>$tenant->id,
            'name'=>'Finance Admin',
            'code'=>'finance-admin',
            'is_system'=>true,
        ]);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::create([
                'module'=>'accounting',
                'resource'=>'finance',
                'action'=>str_replace(['accounting.','fiscal_period.','period.','erp_journal.'],'',$name),
                'name'=>$name,
            ]));
        }

        $user = User::factory()->create([
            'default_tenant_id'=>$tenant->id,
            'default_company_id'=>$company->id,
            'default_branch_id'=>$branch->id,
        ]);

        $membership = Membership::create([
            'tenant_id'=>$tenant->id,
            'user_id'=>$user->id,
            'company_id'=>$company->id,
            'branch_id'=>$branch->id,
            'role_id'=>$role->id,
            'status'=>'active',
            'is_primary'=>true,
        ]);

        return [$tenant,$company,$branch,$user,$membership];
    }

    private function accounts(int $tenantId, int $companyId): array
    {
        $debit = ErpAccount::create([
            'tenant_id'=>$tenantId,
            'company_id'=>$companyId,
            'code'=>'1101',
            'name'=>'Cash Finance',
            'type'=>'asset',
            'normal_balance'=>'debit',
            'is_postable'=>true,
            'is_active'=>true,
        ]);

        $credit = ErpAccount::create([
            'tenant_id'=>$tenantId,
            'company_id'=>$companyId,
            'code'=>'4101',
            'name'=>'Revenue Finance',
            'type'=>'revenue',
            'normal_balance'=>'credit',
            'is_postable'=>true,
            'is_active'=>true,
        ]);

        return [$debit,$credit];
    }

    public function test_closed_fiscal_period_cannot_be_reopened(): void
    {
        [$tenant, $company, , $user] = $this->identity();

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/finance/periods', [
            'year'=>2026,
            'month'=>1,
            'notes'=>'January close',
        ])->assertCreated();

        $periodId = $create->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/finance/periods/{$periodId}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/finance/periods', [
                'year'=>2026,
                'month'=>1,
                'notes'=>'Attempted reopen',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $this->assertSame('closed', FiscalPeriod::query()->findOrFail($periodId)->status);
        $this->assertSame($tenant->id, FiscalPeriod::query()->findOrFail($periodId)->tenant_id);
        $this->assertSame($company->id, FiscalPeriod::query()->findOrFail($periodId)->company_id);
    }

    public function test_journal_cannot_be_posted_into_closed_period(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        [$debit, $credit] = $this->accounts($tenant->id, $company->id);

        $period = FiscalPeriod::create([
            'tenant_id'=>$tenant->id,
            'company_id'=>$company->id,
            'year'=>2026,
            'month'=>2,
            'starts_on'=>'2026-02-01',
            'ends_on'=>'2026-02-28',
            'status'=>'closed',
            'notes'=>'Already closed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/erp/accounting/journals', [
                'branch_id'=>$branch->id,
                'journal_date'=>'2026-02-15',
                'description'=>'Blocked closed-period journal',
                'lines'=>[
                    ['account_id'=>$debit->id,'debit'=>100000,'credit'=>0],
                    ['account_id'=>$credit->id,'debit'=>0,'credit'=>100000],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['journal_date']);

        $this->assertDatabaseMissing('erp_journal_batches', [
            'tenant_id'=>$tenant->id,
            'company_id'=>$company->id,
            'journal_date'=>'2026-02-15',
        ]);

        $this->assertSame('closed', $period->fresh()->status);
    }

    public function test_journal_cannot_be_posted_to_another_branch(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        [$debit, $credit] = $this->accounts($tenant->id, $company->id);
        $otherBranch = Branch::create(['company_id'=>$company->id,'code'=>'BR-X','name'=>'Branch Other']);

        $period = FiscalPeriod::create([
            'tenant_id'=>$tenant->id,
            'company_id'=>$company->id,
            'year'=>2026,
            'month'=>3,
            'starts_on'=>'2026-03-01',
            'ends_on'=>'2026-03-31',
            'status'=>'open',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/erp/accounting/journals', [
                'branch_id'=>$otherBranch->id,
                'journal_date'=>'2026-03-15',
                'description'=>'Blocked cross-branch journal',
                'lines'=>[
                    ['account_id'=>$debit->id,'debit'=>50000,'credit'=>0],
                    ['account_id'=>$credit->id,'debit'=>0,'credit'=>50000],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->assertDatabaseMissing('erp_journal_batches', [
            'tenant_id'=>$tenant->id,
            'company_id'=>$company->id,
            'branch_id'=>$otherBranch->id,
            'journal_date'=>'2026-03-15',
        ]);

        $this->assertDatabaseCount('erp_journal_batches', 0);
        $this->assertNotNull($branch->fresh());
        $this->assertSame('open', $period->fresh()->status);
    }
}
