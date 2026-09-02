<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create demo tenant
        $tenant = Tenant::firstOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Demo Tenant',
                'slug' => 'demo-tenant',
            ]
        );

        // Find or create demo company
        $company = Company::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DEM'],
            [
                'name' => 'Demo Company',
            ]
        );

        // Find or create demo branch
        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HQ'],
            [
                'name' => 'Headquarters',
            ]
        );

        // Find or create demo warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Warehouse',
                'type' => 'main',
            ]
        );

        // Create 3 demo suppliers
        $suppliers = [];
        foreach (['PT Sinar Jaya', 'PT Mitra Terpercaya', 'PT Suplai Nusantara'] as $supplierName) {
            $code = strtoupper(substr($supplierName, -3));
            $suppliers[] = Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => $code],
                [
                    'name' => $supplierName,
                    'tax_id' => sprintf('%s%d', str_pad($code, 3, '0', STR_PAD_LEFT), rand(10000000000, 99999999999)),
                    'contact_name' => 'Contact Person',
                    'phone' => '021-' . rand(10000000, 99999999),
                    'email' => strtolower(str_replace(' ', '', $supplierName)) . '@supplier.co.id',
                    'payment_terms_days' => 30,
                    'status' => 'active',
                ]
            );
        }

        // Create demo category
        $category = Category::firstOrCreate(
            ['name' => 'Demo Products'],
            []
        );

        // Create 5 demo products
        $products = [];
        foreach ([
            ['Kopi Arabika', 50000],
            ['Kopi Robusta', 35000],
            ['Kopi Specialty', 85000],
            ['Gula Pasir', 12000],
            ['Susu Kental Manis', 28000],
        ] as [$productName, $price]) {
            // Use firstOrCreate by name since ID is auto-generated
            $product = Product::where('tenant_id', $tenant->id)
                ->where('name', $productName)
                ->first();
            
            if (!$product) {
                $product = Product::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'category_id' => $category->id,
                    'name' => $productName,
                    'price' => $price,
                ]);
            }
            
            $products[] = $product;
        }

        // Create demo role with permissions
        $role = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'po-manager'],
            [
                'name' => 'PO Manager',
                'is_system' => false,
            ]
        );

        $requiredPermissions = [
            'purchasing.order.view',
            'purchasing.order.create',
            'purchasing.order.submit',
            'purchasing.order.approve',
            'purchasing.order.cancel',
            'purchasing.supplier.view',
        ];

        $permissions = [];
        foreach ($requiredPermissions as $permissionCode) {
            [$module, $resource, $action] = explode('.', $permissionCode);
            $permissions[] = Permission::firstOrCreate([
                'module' => $module,
                'resource' => $resource,
                'action' => $action,
                'name' => $permissionCode,
            ]);
        }

        $role->permissions()->syncWithoutDetaching($permissions);

        // Create demo user with membership
        $user = User::firstOrCreate(
            ['email' => 'po@demo.local'],
            [
                'name' => 'PO Manager Demo',
                'password' => bcrypt('password'),
                'default_tenant_id' => $tenant->id,
                'default_company_id' => $company->id,
                'default_branch_id' => $branch->id,
            ]
        );

        $user->memberships()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'role_id' => $role->id,
                'status' => 'active',
                'is_primary' => true,
            ]
        );

        // Create 5 sample purchase orders with different states
        $statuses = ['draft', 'submitted', 'approved'];
        $dateOffset = 0;

        for ($i = 1; $i <= 5; $i++) {
            $status = $statuses[($i - 1) % count($statuses)];
            $dateOffset -= rand(1, 10);

            $orderNumber = sprintf('PO-%s-%06d', date('Y'), $i);
            $subtotal = rand(5000000, 50000000);
            $discountPercent = rand(0, 10);
            $discount = intdiv($subtotal * $discountPercent, 100);
            $taxPercent = rand(10, 15);
            $tax = intdiv(($subtotal - $discount) * $taxPercent, 100);
            $grandTotal = $subtotal - $discount + $tax;

            $po = PurchaseOrder::firstOrCreate(
                ['order_number' => $orderNumber],
                [
                    'tenant_id' => $tenant->id,
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'warehouse_id' => $warehouse->id,
                    'supplier_id' => $suppliers[($i - 1) % count($suppliers)]->id,
                    'status' => $status,
                    'order_date' => now()->addDays($dateOffset)->toDateString(),
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'grand_total' => $grandTotal,
                    'created_by' => $user->id,
                    'submitted_by' => in_array($status, ['submitted', 'approved']) ? $user->id : null,
                    'submitted_at' => in_array($status, ['submitted', 'approved']) ? now() : null,
                    'approved_by' => $status === 'approved' ? $user->id : null,
                    'approved_at' => $status === 'approved' ? now() : null,
                ]
            );

            // Create 2-4 line items per PO
            $itemCount = rand(2, 4);
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[$j % count($products)];
                $quantity = rand(5, 50);
                $unitCost = rand(10000, 100000);
                $lineTotal = $quantity * $unitCost;

                PurchaseOrderItem::firstOrCreate(
                    ['purchase_order_id' => $po->id, 'product_id' => $product->id],
                    [
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                    ]
                );
            }
        }

        $this->command->info('✓ Purchase Order demo data seeded successfully');
        $this->command->info(sprintf('  - Tenant: %s (%s)', $tenant->name, $tenant->code));
        $this->command->info(sprintf('  - Company: %s', $company->name));
        $this->command->info(sprintf('  - Warehouse: %s', $warehouse->name));
        $this->command->info(sprintf('  - Test User: %s (%s)', $user->name, $user->email));
        $this->command->info(sprintf('  - Suppliers: %d', count($suppliers)));
        $this->command->info(sprintf('  - Products: %d', count($products)));
        $this->command->info(sprintf('  - Purchase Orders: 5'));
    }
}
