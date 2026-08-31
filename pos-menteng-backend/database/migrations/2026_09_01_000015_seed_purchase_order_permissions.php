<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module'=>'purchasing','resource'=>'order','action'=>'view'],
            ['module'=>'purchasing','resource'=>'order','action'=>'create'],
            ['module'=>'purchasing','resource'=>'order','action'=>'submit'],
            ['module'=>'purchasing','resource'=>'order','action'=>'approve'],
            ['module'=>'purchasing','resource'=>'order','action'=>'cancel'],
        ];

        foreach ($permissions as $permission) {
            $name = implode('.', [$permission['module'], $permission['resource'], $permission['action']]);

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                $permission + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $roleIds = DB::table('roles')
            ->whereIn('code', ['tenant-admin', 'purchasing-manager'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_map(
                fn (array $permission) => implode('.', [$permission['module'], $permission['resource'], $permission['action']]),
                $permissions
            ))
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('module', 'purchasing')
            ->where('resource', 'order')
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
