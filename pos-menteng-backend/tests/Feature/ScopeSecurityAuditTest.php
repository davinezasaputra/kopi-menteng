<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\OrganizationScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch;
    private Location $locationA;
    private Location $locationB;
    private Location $office;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private Supplier $supplier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    private function createTestData(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Scope Security Tenant',
            'code' => 'SCOPE',
            'slug' => 'scope-security-tenant',
            'status' => 'active',
        ]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SCOPE-CO',
            'name' => 'Scope Security Company',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'SCOPE-BR',
            'name' => 'Scope Security Branch',
            'status' => 'active',
        ]);

        $this->locationA = Location::create([
            'branch_id' => $this->branch->id,
            'code' => 'LOC-A',
            'name' => 'Location A',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $this->locationB = Location::create([
            'branch_id' => $this->branch->id,
            'code' => 'LOC-B',
            'name' => 'Location B',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $this->office = Location::create([
            'branch_id' => $this->branch->id,
            'code' => 'OFF-1',
            'name' => 'Head Office',
            'type' => 'office',
            'status' => 'active',
        ]);

        $this->warehouseA = Warehouse::create([
            'branch_id' => $this->branch->id,
            'location_id' => $this->locationA->id,
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'type' => 'main',
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->warehouseB = Warehouse::create([
            'branch_id' => $this->branch->id,
            'location_id' => $this->locationB->id,
            'code' => 'WH-B',
            'name' => 'Warehouse B',
            'type' => 'main',
            'is_default' => false,
            'status' => 'active',
        ]);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'SUP-SCOPE',
            'name' => 'Scope Supplier',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Location A Operator',
            'email' => 'scope-a@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Scope Operator',
            'code' => 'scope-operator',
            'is_system' => false,
        ]);

        $permissions = collect([
            'purchasing.order.view',
            'purchasing.order.create',
        ])->map(function (string $permissionCode): Permission {
            [$module, $resource, $action] = explode('.', $permissionCode);

            return Permission::firstOrCreate([
                'module' => $module,
                'resource' => $resource,
                'action' => $action,
                'name' => $permissionCode,
            ]);
        });

        $role->permissions()->attach($permissions);

        $this->user->memberships()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'location_id' => $this->locationA->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    private function makePurchaseOrder(Warehouse $warehouse, string $number): PurchaseOrder
    {
        return PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => $number,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 100000,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_location_scope_only_returns_warehouses_from_active_location(): void
    {
        $membership = $this->user->memberships()->firstOrFail()->load('location', 'branch.company');
        $context = new TenantContext();
        $context->setMembership($membership);

        $scope = new OrganizationScope($context);

        $this->assertSame([$this->warehouseA->id], $scope->warehouseIds());
        $this->assertSame($this->warehouseA->id, $scope->warehouse()->id);
        $this->assertNull($scope->warehouse($this->warehouseB->id));
    }

    public function test_office_location_has_no_operational_warehouse(): void
    {
        $membership = $this->user->memberships()->firstOrFail();
        $membership->update(['location_id' => $this->office->id]);
        $membership->refresh();
        $membership->load('location');

        $context = new TenantContext();
        $context->setMembership($membership);
        $scope = new OrganizationScope($context);

        $this->assertSame([], $scope->warehouseIds());
        $this->assertNull($scope->warehouse());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $scope->requireOperationalLocation();
    }

    public function test_requested_location_header_must_belong_to_user_membership(): void
    {
        $context = new TenantContext();
        $this->expectException(\RuntimeException::class);
        $context->resolveFor($this->user, request()->create('/api/me', 'GET', [], [], [], [
            'HTTP_X-TENANT-ID' => $this->tenant->id,
            'HTTP_X-COMPANY-ID' => $this->company->id,
            'HTTP_X-BRANCH-ID' => $this->branch->id,
            'HTTP_X-LOCATION-ID' => $this->locationB->id,
        ]));
    }

    public function test_purchasing_list_hides_other_location_orders(): void
    {
        $orderA = $this->makePurchaseOrder($this->warehouseA, 'PO-SCOPE-A');
        $orderB = $this->makePurchaseOrder($this->warehouseB, 'PO-SCOPE-B');

        $response = $this->actingAs($this->user)
            ->getJson('/api/purchasing/orders');

        $response->assertOk();

        $rows = data_get($response->json(), 'data.data', []);
        $ids = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($orderA->id, $ids);
        $this->assertNotContains($orderB->id, $ids);
    }

    public function test_purchasing_create_rejects_cross_location_warehouse(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouseB->id,
                'items' => [],
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Warehouse berada di luar organization/location scope aktif.');
    }

    public function test_cross_branch_warehouse_is_rejected_even_without_location_match(): void
    {
        $otherBranch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'SCOPE-B2',
            'name' => 'Other Branch',
            'status' => 'active',
        ]);

        $otherLocation = Location::create([
            'branch_id' => $otherBranch->id,
            'code' => 'LOC-BR2',
            'name' => 'Other Branch Location',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $otherWarehouse = Warehouse::create([
            'branch_id' => $otherBranch->id,
            'location_id' => $otherLocation->id,
            'code' => 'WH-BR2',
            'name' => 'Other Branch Warehouse',
            'type' => 'main',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $otherWarehouse->id,
                'items' => [],
            ]);

        $response->assertForbidden();
    }
}
