<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function identity(string $permissionName = 'users.user.view'): array
    {
        $tenant = Tenant::create(['name'=>'Tenant A','code'=>'TA','slug'=>'tenant-a']);
        $company = Company::create(['tenant_id'=>$tenant->id,'code'=>'CO','name'=>'Company A']);
        $branch = Branch::create(['company_id'=>$company->id,'code'=>'BR','name'=>'Branch A']);
        $permission = Permission::create([
            'module'=>'users','resource'=>'user','action'=>'view','name'=>$permissionName,
        ]);
        $role = Role::create(['tenant_id'=>$tenant->id,'name'=>'Tenant Admin','code'=>'tenant-admin','is_system'=>true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['default_tenant_id'=>$tenant->id,'default_company_id'=>$company->id,'default_branch_id'=>$branch->id]);
        Membership::create([
            'tenant_id'=>$tenant->id,'user_id'=>$user->id,'company_id'=>$company->id,'branch_id'=>$branch->id,
            'role_id'=>$role->id,'status'=>'active','is_primary'=>true,
        ]);
        return [$tenant,$company,$branch,$user,$role];
    }

    public function test_users_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_users_endpoint_is_scoped_to_active_tenant_membership(): void
    {
        [, , , $user] = $this->identity();
        $this->actingAs($user, 'sanctum')->getJson('/api/users')->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $tenant = Tenant::create(['name'=>'Tenant A','code'=>'TA','slug'=>'tenant-a']);
        $company = Company::create(['tenant_id'=>$tenant->id,'code'=>'CO','name'=>'Company A']);
        $branch = Branch::create(['company_id'=>$company->id,'code'=>'BR','name'=>'Branch A']);
        $role = Role::create(['tenant_id'=>$tenant->id,'name'=>'Cashier','code'=>'cashier','is_system'=>true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'company_id'=>$company->id,'branch_id'=>$branch->id,'role_id'=>$role->id,'status'=>'active','is_primary'=>true]);

        $this->actingAs($user, 'sanctum')->getJson('/api/users')->assertForbidden();
    }

    public function test_audit_log_is_immutable(): void
    {
        [$tenant, , , $user] = $this->identity();
        app(\App\Support\Tenancy\TenantContext::class)->setMembership(
            Membership::where('tenant_id',$tenant->id)->where('user_id',$user->id)->firstOrFail()
        );
        $audit = app(AuditService::class)->record('created', 'test', $user);
        $this->expectException(\LogicException::class);
        $audit->update(['event'=>'tampered']);
    }

    public function test_cross_tenant_user_is_not_listed(): void
    {
        [, , , $user] = $this->identity();
        $otherTenant = Tenant::create(['name'=>'Tenant B','code'=>'TB','slug'=>'tenant-b']);
        $otherUser = User::factory()->create();
        $otherRole = Role::create(['tenant_id'=>$otherTenant->id,'name'=>'Admin','code'=>'admin','is_system'=>true]);
        Membership::create(['tenant_id'=>$otherTenant->id,'user_id'=>$otherUser->id,'role_id'=>$otherRole->id,'status'=>'active','is_primary'=>true]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/users')->assertOk();
        $response->assertJsonMissing(['email' => $otherUser->email]);
    }
}
