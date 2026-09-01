<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module'=>'accounting','resource'=>'erp','action'=>'account','name'=>'accounting.erp.account.view'],
            ['module'=>'accounting','resource'=>'erp','action'=>'account','name'=>'accounting.erp.account.create'],
            ['module'=>'accounting','resource'=>'erp','action'=>'journal','name'=>'accounting.erp.journal.view'],
            ['module'=>'accounting','resource'=>'erp','action'=>'journal','name'=>'accounting.erp.journal.create'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name'=>$p['name']],
                [
                    'module'=>$p['module'],
                    'resource'=>$p['resource'],
                    'action'=>$p['action'],
                    'updated_at'=>now(),
                    'created_at'=>now(),
                ]
            );
        }

        $ids=DB::table('permissions')->whereIn('name',array_column($permissions,'name'))->pluck('id');
        $roles=DB::table('roles')->whereIn('code',['tenant-admin','accountant'])->pluck('id');

        foreach($roles as $roleId){
            foreach($ids as $permissionId){
                DB::table('role_permissions')->updateOrInsert([
                    'role_id'=>$roleId,
                    'permission_id'=>$permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids=DB::table('permissions')->whereIn('name',[
            'accounting.erp.account.view',
            'accounting.erp.account.create',
            'accounting.erp.journal.view',
            'accounting.erp.journal.create',
        ])->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};
