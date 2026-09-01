<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceiptTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_can_be_read_and_updated_in_active_scope(): void
    {
        [$user, $tenant, $company, $branch, $role] = $this->makeScope();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/pos/receipt-template');
        $response->assertOk()
            ->assertJsonPath('data.business_name', 'KOPI MENTENG')
            ->assertJsonPath('data.paper_width', '80mm');

        $response = $this->putJson('/api/pos/receipt-template', [
            'business_name' => 'Kopi Menteng Tebet',
            'address' => 'Jl. Tebet Raya',
            'phone' => '0211234567',
            'logo_url' => null,
            'paper_width' => '58mm',
            'show_cashier' => true,
            'show_customer' => false,
            'show_order_type' => true,
            'show_tax' => true,
            'show_discount' => true,
            'show_sku' => false,
            'show_change' => true,
            'footer_text' => 'Terima kasih!',
            'wifi_text' => 'WiFi: KopiMenteng',
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.business_name', 'Kopi Menteng Tebet')
            ->assertJsonPath('data.paper_width', '58mm');
    }

    public function test_template_cannot_be_accessed_across_branch_scope(): void
    {
        [$user, $tenant, $company, $branch] = $this->makeScope();
        Sanctum::actingAs($user);

        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'tenant_id' => $tenant->id,
            'code' => 'OTHER',
            'name' => 'Other Branch',
            'status' => 'active',
        ]);

        $this->assertNotSame($branch->id, $otherBranch->id);

        $this->getJson('/api/pos/receipt-template')->assertOk();
        $this->assertDatabaseMissing('receipt_templates', [
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
        ]);
    }

    private function makeScope(): array
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
        ]);
        $role = Role::factory()->create([
            'tenant_id' => $tenant->id,
            'code' => 'tenant-admin',
        ]);
        $user = User::factory()->create();

        Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        app(\App\Support\Tenancy\TenantContext::class)->set($tenant->id, $company->id, $branch->id, $user->id);

        return [$user, $tenant, $company, $branch, $role];
    }
}
