<?php

namespace Tests\Feature;

use App\Domain\Core\Services\DocumentSequenceService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase1FoundationContractTest extends TestCase
{
    use RefreshDatabase;

    private function identity(string $role = 'tenant-admin'): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'code' => 'TA',
            'slug' => 'tenant-a',
        ]);

        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'CO',
            'name' => 'Company A',
        ]);

        $branch = Branch::create([
            'company_id' => $company->id,
            'code' => 'BR',
            'name' => 'Branch A',
        ]);

        $roleModel = Role::create([
            'tenant_id' => $tenant->id,
            'name' => ucwords(str_replace('-', ' ', $role)),
            'code' => $role,
            'is_system' => true,
        ]);

        $permissions = [
            Permission::create([
                'module' => 'rbac',
                'resource' => 'role',
                'action' => 'view',
                'name' => 'rbac.role.view',
            ]),
            Permission::create([
                'module' => 'audit',
                'resource' => 'audit_log',
                'action' => 'view',
                'name' => 'audit.audit_log.view',
            ]),
        ];

        $roleModel->permissions()->attach($permissions);

        $user = User::factory()->create([
            'default_tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
        ]);

        Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $roleModel->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        return [$tenant, $company, $branch, $user, $roleModel];
    }

    public function test_v1_me_exposes_scoped_permissions(): void
    {
        $identity = $this->identity();
        $branch = $identity[2];
        $user = $identity[3];

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonStructure([
                'data' => [
                    'tenant_id',
                    'company_id',
                    'branch_id',
                    'role',
                    'permissions',
                ],
            ]);
    }

    public function test_cross_tenant_context_header_is_rejected(): void
    {
        $identity = $this->identity();
        $tenantA = $identity[0];
        $user = $identity[3];

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'code' => 'TB',
            'slug' => 'tenant-b',
        ]);

        $companyB = Company::create([
            'tenant_id' => $tenantB->id,
            'code' => 'CO',
            'name' => 'Company B',
        ]);

        $branchB = Branch::create([
            'company_id' => $companyB->id,
            'code' => 'BR',
            'name' => 'Branch B',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $tenantB->id,
                'X-Company-ID' => $companyB->id,
                'X-Branch-ID' => $branchB->id,
            ])
            ->getJson('/api/v1/me');

        $response->assertForbidden();
        $this->assertSame($tenantA->id, app(TenantContext::class)->tenantId() ?? $tenantA->id);
    }

    public function test_document_numbers_are_sequential_and_unique(): void
    {
        $identity = $this->identity();
        $company = $identity[1];
        $branch = $identity[2];
        $user = $identity[3];

        $membership = Membership::where('user_id', $user->id)->firstOrFail();
        app(TenantContext::class)->setMembership($membership);

        $service = app(DocumentSequenceService::class);
        $a = $service->next('invoice', 'INV', 6);
        $b = $service->next('invoice', 'INV', 6);

        $this->assertNotSame($a, $b);
        $this->assertSame('INV-' . now()->format('Ym') . '-000001', $a);
        $this->assertSame('INV-' . now()->format('Ym') . '-000002', $b);
        $this->assertSame($company->id, $membership->company_id);
        $this->assertSame($branch->id, $membership->branch_id);
    }

    public function test_system_role_cannot_be_deleted_by_policy(): void
    {
        $identity = $this->identity();
        $user = $identity[3];
        $role = $identity[4];

        app(TenantContext::class)->setMembership(
            Membership::where('user_id', $user->id)->firstOrFail()
        );

        $this->assertFalse($user->can('delete', $role));
    }

    public function test_pin_is_not_exposed_as_plaintext(): void
    {
        $user = User::factory()->create(['pin' => '123456']);
        $user->refresh();

        $this->assertNull($user->pin);
        $this->assertNotNull($user->pin_hash);
        $this->assertTrue(Hash::check('123456', $user->pin_hash));
        $this->assertArrayNotHasKey('pin', $user->toArray());
    }
}
