<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\SalesOrder;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationScopeSalesHrmAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch;
    private Location $locationA;
    private Location $locationB;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Sales HRM Scope Tenant',
            'code' => 'SAHR',
            'slug' => 'sales-hrm-scope-tenant',
            'status' => 'active',
        ]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SAHR-CO',
            'name' => 'Sales HRM Scope Company',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'SAHR-BR',
            'name' => 'Sales HRM Scope Branch',
            'status' => 'active',
        ]);

        $this->locationA = Location::create([
            'branch_id' => $this->branch->id,
            'code' => 'SAHR-A',
            'name' => 'Sales HRM Location A',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $this->locationB = Location::create([
            'branch_id' => $this->branch->id,
            'code' => 'SAHR-B',
            'name' => 'Sales HRM Location B',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $this->warehouseA = Warehouse::create([
            'branch_id' => $this->branch->id,
            'location_id' => $this->locationA->id,
            'code' => 'SAHR-WHA',
            'name' => 'Sales HRM Warehouse A',
            'type' => 'main',
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->warehouseB = Warehouse::create([
            'branch_id' => $this->branch->id,
            'location_id' => $this->locationB->id,
            'code' => 'SAHR-WHB',
            'name' => 'Sales HRM Warehouse B',
            'type' => 'main',
            'is_default' => false,
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Scope Sales Category']);
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Scope Sales Product',
            'price' => 100000,
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Location A Sales HRM Operator',
            'email' => 'sales-hrm-scope-a@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales HRM Scope Operator',
            'code' => 'sales-hrm-scope-operator',
            'is_system' => false,
        ]);

        $permissionCodes = [
            'sales.order.view',
            'sales.order.create',
            'sales.order.submit',
            'sales.order.cancel',
            'hr.employee.view',
            'hr.employee.manage',
        ];

        $permissions = collect($permissionCodes)->map(function (string $permissionCode): Permission {
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

    private function makeSalesOrder(Warehouse $warehouse, string $number): SalesOrder
    {
        return SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'location_id' => $warehouse->location_id,
            'warehouse_id' => $warehouse->id,
            'order_number' => $number,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 100000,
            'created_by' => $this->user->id,
        ]);
    }

    private function makeEmployee(Location $location, string $name): Employee
    {
        return Employee::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'location_id' => $location->id,
            'name' => $name,
            'WA' => '08120000000',
            'position' => 'Staff',
            'base_sallary' => 5000000,
            'status' => 'active',
        ]);
    }

    public function test_sales_order_list_hides_other_location_orders(): void
    {
        $orderA = $this->makeSalesOrder($this->warehouseA, 'SO-SCOPE-A');
        $orderB = $this->makeSalesOrder($this->warehouseB, 'SO-SCOPE-B');

        $response = $this->actingAs($this->user)->getJson('/api/sales/orders');

        $response->assertOk();
        $ids = collect(data_get($response->json(), 'data.data', []))
            ->pluck('id')
            ->all();

        $this->assertContains($orderA->id, $ids);
        $this->assertNotContains($orderB->id, $ids);
    }

    public function test_sales_order_cross_location_mutations_are_hidden(): void
    {
        $orderB = $this->makeSalesOrder($this->warehouseB, 'SO-SCOPE-B-MUTATION');

        $this->actingAs($this->user)
            ->postJson('/api/sales/orders/' . $orderB->id . '/submit')
            ->assertNotFound();

        $this->actingAs($this->user)
            ->postJson('/api/sales/orders/' . $orderB->id . '/cancel')
            ->assertNotFound();

        $this->assertDatabaseHas('sales_orders', [
            'id' => $orderB->id,
            'status' => 'draft',
        ]);
    }

    public function test_sales_order_create_rejects_other_location_warehouse(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/sales/orders', [
            'warehouse_id' => $this->warehouseB->id,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100000,
            ]],
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Warehouse berada di luar organization/location scope aktif.');
    }

    public function test_employee_list_and_show_hide_other_location_records(): void
    {
        $employeeA = $this->makeEmployee($this->locationA, 'Employee A');
        $employeeB = $this->makeEmployee($this->locationB, 'Employee B');

        $list = $this->actingAs($this->user)->getJson('/api/employees');
        $list->assertOk();

        $ids = collect(data_get($list->json(), 'data.data', []))->pluck('id')->all();
        $this->assertContains($employeeA->id, $ids);
        $this->assertNotContains($employeeB->id, $ids);

        $this->actingAs($this->user)
            ->getJson('/api/employees/' . $employeeB->id)
            ->assertNotFound();
    }

    public function test_employee_update_and_delete_cannot_cross_location(): void
    {
        $employeeB = $this->makeEmployee($this->locationB, 'Employee B Mutation');

        $this->actingAs($this->user)
            ->putJson('/api/employees/' . $employeeB->id, [
                'name' => 'Tampered Employee B',
            ])
            ->assertNotFound();

        $this->actingAs($this->user)
            ->deleteJson('/api/employees/' . $employeeB->id)
            ->assertNotFound();

        $this->assertDatabaseHas('employees', [
            'id' => $employeeB->id,
            'name' => 'Employee B Mutation',
            'location_id' => $this->locationB->id,
        ]);
    }
}
