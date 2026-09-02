<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Services\PosOrderAccountingService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosOrderAccountingClosingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant POS Accounting', 'code' => 'TPOSA', 'slug' => 'tenant-pos-accounting']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'CPOSA', 'name' => 'Company POS Accounting']);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'BR-POSA', 'name' => 'Branch POS Accounting']);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'POS Accounting Admin',
            'code' => 'pos-accounting-admin',
            'is_system' => false,
        ]);

        foreach (['pos.sale.view', 'pos.sale.create'] as $name) {
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
            ['1010', 'Bank', 'asset', 'debit'],
            ['4000', 'Sales Revenue', 'revenue', 'credit'],
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

    private function order(Tenant $tenant, Company $company, Branch $branch, User $user, string $status = 'paid'): Order
    {
        $shift = Shift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'start_time' => '2026-09-15 08:00:00',
            'starting_cash' => 0,
            'expected_ending_cash' => 0,
            'status' => 'open',
        ]);

        return Order::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'invoice_number' => 'INV-TEST-001',
            'subtotal' => 100000,
            'tax' => 0,
            'discount' => 0,
            'total' => 100000,
            'total_cogs' => 0,
            'net_profit' => 100000,
            'payment_method' => 'cash',
            'status' => $status,
            'created_at' => Carbon::parse('2026-09-15 10:00:00'),
            'updated_at' => Carbon::parse('2026-09-15 10:00:00'),
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

    public function test_paid_pos_order_posts_erp_journal_and_uses_order_business_date(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $order = $this->order($tenant, $company, $branch, $user);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);

        $this->app->make(PosOrderAccountingService::class)->postPaidOrder($order);

        $this->assertDatabaseCount('erp_journal_batches', 1);
        $batch = ErpJournalBatch::query()->firstOrFail();
        $this->assertSame('2026-09-15', $batch->journal_date->toDateString());
        $this->assertSame('pos_sale_payment', $batch->source_type);
        $this->assertSame((string) $order->id, (string) $batch->source_id);
        $this->assertSame($branch->id, $batch->branch_id);
        $this->assertSame(100000.0, (float) $batch->total_debit);
        $this->assertSame(100000.0, (float) $batch->total_credit);
    }

    public function test_pos_order_accounting_is_blocked_by_closed_period(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $order = $this->order($tenant, $company, $branch, $user);
        $this->period($tenant, $company, 'closed');
        $this->setContext($tenant, $company, $branch);

        $this->expectException(ValidationException::class);
        try {
            $this->app->make(PosOrderAccountingService::class)->postPaidOrder($order);
        } finally {
            $this->assertDatabaseCount('erp_journal_batches', 0);
        }
    }

    public function test_duplicate_pos_order_payment_is_idempotent(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $order = $this->order($tenant, $company, $branch, $user);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);

        $service = $this->app->make(PosOrderAccountingService::class);
        $service->postPaidOrder($order);
        $service->postPaidOrder($order->fresh());

        $this->assertDatabaseCount('erp_journal_batches', 1);
    }

    public function test_cross_company_pos_order_is_rejected(): void
    {
        [$tenant, $company, $branch, $user] = $this->identity();
        $this->accounts($tenant->id, $company->id);
        $this->period($tenant, $company, 'open');
        $this->setContext($tenant, $company, $branch);

        $otherCompany = Company::create(['tenant_id' => $tenant->id, 'code' => 'CPOSA-2', 'name' => 'Other POS Company']);
        $otherBranch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $otherCompany->id, 'code' => 'BR-POSA-2', 'name' => 'Other POS Branch']);
        $order = $this->order($tenant, $otherCompany, $otherBranch, $user);

        $this->expectException(ValidationException::class);
        $this->app->make(PosOrderAccountingService::class)->postPaidOrder($order);
    }
}
