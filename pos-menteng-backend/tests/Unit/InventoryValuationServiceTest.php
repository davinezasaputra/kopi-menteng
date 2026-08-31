<?php

namespace Tests\Unit;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Services\InventoryValuationService;
use App\Support\Tenancy\TenantContext;
use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_value_is_quantity_times_average_cost(): void
    {
        $tenant = \App\Domain\Organization\Models\Tenant::create([
            'name' => 'Tenant Valuation',
            'code' => 'TV',
            'slug' => 'tenant-valuation',
        ]);
        $company = \App\Domain\Organization\Models\Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'TVC',
            'name' => 'Valuation Company',
        ]);
        $branch = \App\Domain\Organization\Models\Branch::create([
            'company_id' => $company->id,
            'code' => 'TVB',
            'name' => 'Valuation Branch',
        ]);
        $warehouse = \App\Domain\Organization\Models\Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'TVW',
            'name' => 'Valuation Warehouse',
        ]);
        $role = \App\Domain\Identity\Models\Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Valuation',
            'code' => 'valuation',
            'is_system' => true,
        ]);
        $user = \App\Models\User::factory()->create();
        $membership = Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
        app(TenantContext::class)->setMembership($membership);

        $product = \App\Models\Product::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        InventoryBalance::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'reserved_quantity' => 5,
            'available_quantity' => 15,
            'average_cost' => 12500,
            'last_cost' => 13000,
        ]);

        $result = app(InventoryValuationService::class)->summary();

        $this->assertSame(20.0, $result['total_quantity']);
        $this->assertSame(5.0, $result['total_reserved_quantity']);
        $this->assertSame(15.0, $result['total_available_quantity']);
        $this->assertSame(250000.0, $result['total_inventory_value']);
        $this->assertSame(250000.0, $result['items'][0]['inventory_value']);
    }
}
