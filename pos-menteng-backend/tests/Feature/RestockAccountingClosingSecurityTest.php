<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Services\RestockAccountingService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\JournalEntry;
use App\Models\RawMaterial;
use App\Models\RestockHistory;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestockAccountingClosingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant Restock Accounting', 'code' => 'TRA', 'slug' => 'tenant-restock-accounting']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'CRA', 'name' => 'Company Restock Accounting']);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'BR-RA', 'name' => 'Branch Restock Accounting']);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Restock Admin',
            'code' => 'restock-admin',
            'is_system' => false,
        ]);

        [$module, $resource, $action] = explode('.', 'inventory.stock.adjust');
        $permission = Permission::firstOrCreate([
            'module' => $module,
            'resource' => $resource,
            'action' => $action,
            'name' => 'inventory.stock.adjust',
        ]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

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
            ['1100', 'Inventory Asset', 'asset', 'debit'],
            ['1000', 'Cash', 'asset', 'debit'],
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

    private function restock(Tenant $tenant, Company $company, Branch $branch, string $status = 'normal'): RestockHistory
    {
        $material = RawMaterial::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Coffee Beans '.$status,
            'category' => 'bar',
            'unit' => 'kg',
            'stock' => 10,
            'price_per_unit' => 80000,
            'min_stock_level' => 2,
            'is_requested' => false,
        ]);

        $restock = RestockHistory::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'raw_material_id' => $material->id,
            'quantity_added' => 5,
            'total_cost' => 400000,
            'restocked_by' => 'Restock User',
        ]);

        $restock->forceFill([
            'created_at' => Carbon::parse('2026-09-15 10:00:00'),
            'updated_at' => Carbon::parse('2026-09-15 10:00:00'),
        ])->saveQuietly();

        return $restock->fresh();
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

    public function test_restock_posts_erp_journal_on_restock_business_date(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $restock = $this->restock($tenant, $company, $branch);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);
        $this->actingAs($user, 'sanctum');

        $this->app->make(RestockAccountingService::class)->postRestock($restock);

        $this->assertDatabaseCount('erp_journal_batches', 1);
        $batch = ErpJournalBatch::query()->firstOrFail();
        $this->assertSame('2026-09-15', $batch->journal_date->toDateString());
        $this->assertSame('inventory_restock', $batch->source_type);
        $this->assertSame((string) $restock->id, (string) $batch->source_id);
        $this->assertSame($branch->id, $batch->branch_id);
        $this->assertSame($user->id, $batch->created_by);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_restock_accounting_is_blocked_by_closed_period(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $restock = $this->restock($tenant, $company, $branch);
        $this->period($tenant, $company, 'closed');
        $this->setContext($tenant, $company, $branch);
        $this->actingAs($user, 'sanctum');

        $this->expectException(ValidationException::class);
        try {
            $this->app->make(RestockAccountingService::class)->postRestock($restock);
        } finally {
            $this->assertDatabaseCount('erp_journal_batches', 0);
        }
    }

    public function test_duplicate_restock_accounting_is_idempotent(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $restock = $this->restock($tenant, $company, $branch);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);
        $this->actingAs($user, 'sanctum');

        $service = $this->app->make(RestockAccountingService::class);
        $service->postRestock($restock);
        $service->postRestock($restock->fresh());

        $this->assertDatabaseCount('erp_journal_batches', 1);
    }

    public function test_cross_company_restock_is_rejected(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);
        $this->actingAs($user, 'sanctum');

        $otherCompany = Company::create(['tenant_id' => $tenant->id, 'code' => 'CRA-2', 'name' => 'Other Restock Company']);
        $otherBranch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $otherCompany->id, 'code' => 'BR-RA-2', 'name' => 'Other Restock Branch']);
        $restock = $this->restock($tenant, $otherCompany, $otherBranch, 'cross-company');

        $this->expectException(ValidationException::class);
        $this->app->make(RestockAccountingService::class)->postRestock($restock);
    }
}
