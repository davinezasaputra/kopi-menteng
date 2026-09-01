<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module'=>'accounting','resource'=>'erp.account','action'=>'view'],
            ['module'=>'accounting','resource'=>'erp.account','action'=>'create'],
            ['module'=>'accounting','resource'=>'erp.journal','action'=>'view'],
            ['module'=>'accounting','resource'=>'erp.journal','action'=>'create'],
        ];

        foreach ($permissions as $permission) {
            $name = implode('.', [$permission['module'], $permission['resource'], $permission['action']]);
            DB::table('permissions')->updateOrInsert(
                ['name'=>$name],
                $permission + ['created_at'=>now(),'updated_at'=>now()],
            );
        }

        $roleIds = DB::table('roles')
            ->whereIn('code',['tenant-admin','accountant'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name',[
                'accounting.erp.account.view',
                'accounting.erp.account.create',
                'accounting.erp.journal.view',
                'accounting.erp.journal.create',
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
        $ids=DB::table('permissions')
            ->whereIn('name',[
                'accounting.erp.account.view',
                'accounting.erp.account.create',
                'accounting.erp.journal.view',
                'accounting.erp.journal.create',
            ])->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};
