<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module'=>'purchasing','resource'=>'ap','action'=>'view'],
            ['module'=>'purchasing','resource'=>'ap','action'=>'create'],
            ['module'=>'purchasing','resource'=>'ap','action'=>'pay'],
        ];

        foreach ($permissions as $permission) {
            $name = implode('.', [$permission['module'], $permission['resource'], $permission['action']]);

            DB::table('permissions')->updateOrInsert(
                ['name'=>$name],
                $permission + ['created_at'=>now(),'updated_at'=>now()],
            );
        }

        $roleIds = DB::table('roles')
            ->whereIn('code',['tenant-admin','purchasing-manager'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name',[
                'purchasing.ap.view',
                'purchasing.ap.create',
                'purchasing.ap.pay',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id'=>$roleId,
                    'permission_id'=>$permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name',[
                'purchasing.ap.view',
                'purchasing.ap.create',
                'purchasing.ap.pay',
            ])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id',$permissionIds)->delete();
        DB::table('permissions')->whereIn('id',$permissionIds)->delete();
    }
};
