<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Seeder;

class Phase1PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['users','user','view'],['users','user','create'],['users','user','update'],['users','user','delete'],
            ['rbac','role','view'],['rbac','role','manage'],['audit','audit_log','view'],
            ['organization','organization','view'],['organization','branch','view'],['organization','branch','manage'],
            ['pos','sale','view'],['pos','sale','create'],
            ['crm','customer','view'],['crm','customer','create'],
            ['hr','employee','view'],['hr','employee','manage'],
        ];

        $permissions = [];
        foreach ($definitions as [$module,$resource,$action]) {
            $name = "{$module}.{$resource}.{$action}";
            $permissions[$name] = Permission::updateOrCreate(['name'=>$name], ['module'=>$module,'resource'=>$resource,'action'=>$action]);
        }

        $all = collect($permissions)->pluck('id')->all();
        foreach (Tenant::query()->get() as $tenant) {
            $admin = Role::query()->where('tenant_id',$tenant->id)->where('code','tenant-admin')->first();
            if ($admin) $admin->permissions()->syncWithoutDetaching($all);

            $cashier = Role::query()->where('tenant_id',$tenant->id)->where('code','cashier')->first();
            if ($cashier) {
                $cashier->permissions()->syncWithoutDetaching(array_filter([
                    $permissions['pos.sale.view']->id ?? null,
                    $permissions['pos.sale.create']->id ?? null,
                    $permissions['crm.customer.view']->id ?? null,
                ]));
            }

            $hr = Role::query()->where('tenant_id',$tenant->id)->where('code','hr-manager')->first();
            if ($hr) $hr->permissions()->syncWithoutDetaching(array_filter([$permissions['hr.employee.view']->id ?? null, $permissions['hr.employee.manage']->id ?? null]));
        }
    }
}
