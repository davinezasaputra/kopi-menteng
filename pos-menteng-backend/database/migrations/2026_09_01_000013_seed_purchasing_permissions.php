<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module'=>'purchasing','resource'=>'supplier','action'=>'view'],
            ['module'=>'purchasing','resource'=>'supplier','action'=>'create'],
            ['module'=>'purchasing','resource'=>'requisition','action'=>'view'],
            ['module'=>'purchasing','resource'=>'requisition','action'=>'create'],
            ['module'=>'purchasing','resource'=>'requisition','action'=>'submit'],
            ['module'=>'purchasing','resource'=>'requisition','action'=>'cancel'],
        ];

        foreach ($permissions as $permission) {
            $name = implode('.', [$permission['module'], $permission['resource'], $permission['action']]);

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                $permission + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $tenantAdmins = DB::table('roles')
            ->where('code', 'tenant-admin')
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_map(
                fn (array $permission) => implode('.', [$permission['module'], $permission['resource'], $permission['action']]),
                $permissions
            ))
            ->pluck('id');

        foreach ($tenantAdmins as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('module', 'purchasing')
            ->whereIn('resource', ['supplier', 'requisition'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
