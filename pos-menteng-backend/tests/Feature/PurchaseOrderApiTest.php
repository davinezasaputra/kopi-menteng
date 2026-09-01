<?php

namespace Tests\Feature;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Purchasing\Models\Supplier;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch;
    private Warehouse $warehouse;
    private Supplier $supplier;
    private Category $category;
    private Product $product;
    private Role $role;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create tenant/company/branch/warehouse
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'code' => 'TEST',
            'slug' => 'test-tenant',
        ]);
        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST',
            'name' => 'Test Company',
        ]);
        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'BDG',
            'name' => 'Bandung',
        ]);
        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'code' => 'WH1',
            'name' => 'Warehouse 1',
            'type' => 'main',
        ]);

        // Create supplier
        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'SUP001',
            'name' => 'PT Supplier ABC',
            'tax_id' => '123456789012',
            'contact_name' => 'John Supplier',
            'phone' => '081234567890',
            'email' => 'supplier@example.com',
            'payment_terms_days' => 30,
            'status' => 'active',
        ]);

        // Create category
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        // Create product
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD001',
            'name' => 'Test Product',
            'price' => 100000,
            'status' => 'active',
        ]);

        // Create user with proper tenant/company/branch membership
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create role
        $this->role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Role',
            'code' => 'test-role',
            'is_system' => false,
        ]);

        // Create and attach permissions
        $permissions = [];
        $requiredPermissions = [
            'purchasing.order.view',
            'purchasing.order.create',
            'purchasing.order.submit',
            'purchasing.order.approve',
            'purchasing.order.cancel',
            'purchasing.supplier.view',
        ];

        foreach ($requiredPermissions as $permissionCode) {
            [$module, $resource, $action] = explode('.', $permissionCode);
            $permissions[] = Permission::firstOrCreate([
                'module' => $module,
                'resource' => $resource,
                'action' => $action,
                'name' => $permissionCode,
            ]);
        }

        $this->role->permissions()->attach($permissions);

        // Create membership
        $this->user->memberships()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'role_id' => $this->role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    // ========== AUTHENTICATION & AUTHORIZATION TESTS ==========

    public function test_create_purchase_order_requires_authentication(): void
    {
        $response = $this->postJson('/api/purchasing/orders', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_cost' => 100000,
                ],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_purchase_order_with_cross_tenant_supplier_fails(): void
    {
        // Create another tenant's supplier
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'code' => 'OTHER',
            'slug' => 'other-tenant',
        ]);
        $otherCompany = Company::create([
            'tenant_id' => $otherTenant->id,
            'code' => 'OTHER',
            'name' => 'Other Company',
        ]);
        $otherSupplier = Supplier::create([
            'tenant_id' => $otherTenant->id,
            'company_id' => $otherCompany->id,
            'code' => 'SUP002',
            'name' => 'Other Supplier',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $otherSupplier->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Supplier is outside the active company.');
    }

    public function test_create_purchase_order_with_cross_branch_warehouse_fails(): void
    {
        // Create another branch's warehouse
        $otherBranch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'JKT',
            'name' => 'Jakarta',
        ]);
        $otherWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $otherBranch->id,
            'code' => 'WH2',
            'name' => 'Warehouse 2',
            'type' => 'main',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $otherWarehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Warehouse is outside the active branch.');
    }

    // ========== RESPONSE SHAPE TESTS ==========

    public function test_list_purchase_orders_returns_proper_response_shape(): void
    {
        // Create a PO
        PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-2026-000001',
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 1000000,
            'discount_amount' => 0,
            'tax_amount' => 100000,
            'grand_total' => 1100000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/purchasing/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'order_number',
                            'status',
                            'order_date',
                            'expected_date',
                            'subtotal',
                            'discount_amount',
                            'tax_amount',
                            'grand_total',
                            'supplier' => [
                                'id',
                                'name',
                                'code',
                            ],
                            'warehouse' => [
                                'id',
                                'name',
                            ],
                            'items' => [
                                '*' => [
                                    'id',
                                    'product_id',
                                    'quantity',
                                    'unit_cost',
                                    'line_total',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify supplier is object not string/null
        $suppliers = data_get($response->json(), 'data.data.*.supplier');
        foreach ($suppliers as $supplier) {
            $this->assertIsArray($supplier);
            $this->assertArrayHasKey('name', $supplier);
            $this->assertIsString($supplier['name']);
            $this->assertNotEmpty($supplier['name']);
        }
    }

    public function test_order_date_is_business_date_not_utc(): void
    {
        $businessDate = now()->toDateString(); // e.g., "2026-09-02"

        PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-2026-000002',
            'status' => 'draft',
            'order_date' => $businessDate,
            'subtotal' => 500000,
            'discount_amount' => 0,
            'tax_amount' => 50000,
            'grand_total' => 550000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/purchasing/orders');

        $orderDate = data_get($response->json(), 'data.data.0.order_date');
        
        // Should be business date format - extract date part from timestamp
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $orderDate);
        // Extract just the date portion if it's an ISO timestamp
        $datePortion = substr($orderDate, 0, 10);
        $this->assertEquals($businessDate, $datePortion);
    }

    // ========== CALCULATION VALIDATION TESTS ==========

    public function test_purchase_order_calculation_is_correct(): void
    {
        $subtotal = 1000000;
        $discount = 100000;
        $tax = 90000;
        $expectedGrandTotal = $subtotal - $discount + $tax; // 990000

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $response->assertCreated();

        // grand_total might be returned as string from decimal field
        $responseGrandTotal = data_get($response->json(), 'data.grand_total');
        $this->assertEquals((string) $expectedGrandTotal, (string) round((float) $responseGrandTotal, 0));

        // Verify in database
        $po = PurchaseOrder::latest()->first();
        $this->assertEquals($expectedGrandTotal, $po->grand_total);
    }

    public function test_purchase_order_item_line_total_calculated_correctly(): void
    {
        $quantity = 5;
        $unitCost = 250000;
        $expectedLineTotal = $quantity * $unitCost; // 1250000

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                    ],
                ],
            ]);

        $response->assertCreated();

        $item = PurchaseOrderItem::latest()->first();
        $this->assertEquals($quantity, $item->quantity);
        $this->assertEquals($unitCost, $item->unit_cost);
        $this->assertEquals($expectedLineTotal, $item->line_total);
    }

    // ========== WORKFLOW STATE TESTS ==========

    public function test_purchase_order_starts_in_draft_status(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $po = PurchaseOrder::latest()->first();
        $this->assertEquals('draft', $po->status);
    }

    public function test_draft_purchase_order_can_transition_to_submitted(): void
    {
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-2026-000003',
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 500000,
            'discount_amount' => 0,
            'tax_amount' => 50000,
            'grand_total' => 550000,
            'created_by' => $this->user->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 100000,
            'line_total' => 500000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchasing/orders/{$po->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertEquals('submitted', $po->fresh()->status);
        $this->assertNotNull($po->fresh()->submitted_at);
        $this->assertEquals($this->user->id, $po->fresh()->submitted_by);
    }

    public function test_empty_purchase_order_cannot_be_submitted(): void
    {
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-2026-000004',
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 0,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchasing/orders/{$po->id}/submit");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Purchase order must contain at least one item.');
    }

    // ========== PRODUCT ID TYPE TESTS ==========

    public function test_product_id_uuid_is_not_converted_to_number(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/purchasing/orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id, // UUID string
                        'quantity' => 5,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $response->assertCreated();

        $item = PurchaseOrderItem::latest()->first();
        // Product ID should remain as UUID string, not become integer
        $this->assertIsString($item->product_id);
        $this->assertEquals($this->product->id, $item->product_id);
    }
}
