<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name'=>'purchasing.reporting.view'],
            ['module'=>'purchasing','resource'=>'reporting','action'=>'view','created_at'=>now(),'updated_at'=>now()],
        );

        $permissionId=DB::table('permissions')->where('name','purchasing.reporting.view')->value('id');

        $roleIds=DB::table('roles')->whereIn('code',['tenant-admin','purchasing-manager'])->pluck('id');

        foreach($roleIds as $roleId){
            DB::table('role_permissions')->updateOrInsert([
                'role_id'=>$roleId,
                'permission_id'=>$permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId=DB::table('permissions')->where('name','purchasing.reporting.view')->value('id');

        if($permissionId){
            DB::table('role_permissions')->where('permission_id',$permissionId)->delete();
            DB::table('permissions')->where('id',$permissionId)->delete();
        }
    }
};
