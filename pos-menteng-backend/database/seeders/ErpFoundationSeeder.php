<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ErpFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(['code' => 'DEMO'], [
            'name' => 'Kopi Menteng Demo', 'slug' => 'kopi-menteng-demo',
            'status' => 'active', 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR',
        ]);

        $company = Company::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'KM'], [
            'name' => 'Kopi Menteng Indonesia', 'status' => 'active',
            'timezone' => 'Asia/Jakarta', 'currency' => 'IDR',
        ]);

        $branch = Branch::firstOrCreate(['company_id' => $company->id, 'code' => 'MTG'], [
            'name' => 'Kopi Menteng - Menteng', 'type' => 'store', 'status' => 'active',
        ]);

        $permissions = [
            ['module'=>'users','resource'=>'user','action'=>'view'],
            ['module'=>'users','resource'=>'user','action'=>'create'],
            ['module'=>'users','resource'=>'user','action'=>'update'],
            ['module'=>'users','resource'=>'user','action'=>'delete'],
            ['module'=>'rbac','resource'=>'role','action'=>'view'],
            ['module'=>'rbac','resource'=>'role','action'=>'manage'],
            ['module'=>'audit','resource'=>'audit_log','action'=>'view'],
            ['module'=>'organization','resource'=>'branch','action'=>'view'],
            ['module'=>'organization','resource'=>'branch','action'=>'manage'],
            ['module'=>'pos','resource'=>'sale','action'=>'view'],
            ['module'=>'pos','resource'=>'sale','action'=>'create'],
            ['module'=>'inventory','resource'=>'stock','action'=>'view'],
            ['module'=>'inventory','resource'=>'stock','action'=>'adjust'],
            ['module'=>'accounting','resource'=>'journal','action'=>'view'],
            ['module'=>'accounting','resource'=>'journal','action'=>'create'],
            ['module'=>'accounting','resource'=>'journal','action'=>'post'],
            ['module'=>'hr','resource'=>'employee','action'=>'view'],
            ['module'=>'hr','resource'=>'employee','action'=>'manage'],
        ];

        $permissionModels = collect($permissions)->mapWithKeys(function (array $permission) {
            $name = implode('.', [$permission['module'], $permission['resource'], $permission['action']]);
            return [$name => Permission::firstOrCreate(['name' => $name], $permission)];
        });

        $roleDefinitions = [
            'tenant-admin' => ['name'=>'Tenant Admin', 'permissions'=>$permissionModels->keys()->all()],
            'branch-manager' => ['name'=>'Branch Manager', 'permissions'=>['pos.sale.view','pos.sale.create','inventory.stock.view','inventory.stock.adjust','organization.branch.view','hr.employee.view']],
            'cashier' => ['name'=>'Cashier', 'permissions'=>['pos.sale.view','pos.sale.create']],
            'accountant' => ['name'=>'Accountant', 'permissions'=>['accounting.journal.view','accounting.journal.create','accounting.journal.post']],
            'warehouse-manager' => ['name'=>'Warehouse Manager', 'permissions'=>['inventory.stock.view','inventory.stock.adjust']],
            'hr-manager' => ['name'=>'HR Manager', 'permissions'=>['hr.employee.view','hr.employee.manage']],
        ];

        $roles = [];
        foreach ($roleDefinitions as $code => $definition) {
            $role = Role::firstOrCreate(['tenant_id'=>$tenant->id, 'code'=>$code], [
                'name'=>$definition['name'], 'is_system'=>true,
            ]);
            $role->permissions()->sync($permissionModels->only($definition['permissions'])->pluck('id')->all());
            $roles[$code] = $role;
        }

        foreach (User::query()->get() as $user) {
            $roleCode = match ($user->role) {
                'developer', 'owner', 'admin' => 'tenant-admin',
                'manager' => 'branch-manager',
                'kasir' => 'cashier',
                default => 'cashier',
            };

            Membership::updateOrCreate(
                ['tenant_id'=>$tenant->id, 'user_id'=>$user->id, 'role_id'=>$roles[$roleCode]->id, 'company_id'=>$company->id, 'branch_id'=>$branch->id],
                ['status'=>'active', 'is_primary'=>true]
            );

            $user->forceFill([
                'default_tenant_id'=>$tenant->id,
                'default_company_id'=>$company->id,
                'default_branch_id'=>$branch->id,
            ])->saveQuietly();
        }
    }
}
