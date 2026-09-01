<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions=[
            ['module'=>'sales','resource'=>'fulfillment','action'=>'view'],
            ['module'=>'sales','resource'=>'fulfillment','action'=>'create'],
            ['module'=>'sales','resource'=>'fulfillment','action'=>'pick'],
            ['module'=>'sales','resource'=>'fulfillment','action'=>'pack'],
        ];

        foreach($permissions as $permission){
            $name=implode('.',[$permission['module'],$permission['resource'],$permission['action']]);
            DB::table('permissions')->updateOrInsert(
                ['name'=>$name],
                $permission+['created_at'=>now(),'updated_at'=>now()]
            );
        }

        $ids=DB::table('permissions')->whereIn('name',[
            'sales.fulfillment.view','sales.fulfillment.create',
            'sales.fulfillment.pick','sales.fulfillment.pack',
        ])->pluck('id');

        $roles=DB::table('roles')->whereIn('code',['tenant-admin','sales-manager','warehouse-manager'])->pluck('id');

        foreach($roles as $roleId){
            foreach($ids as $permissionId){
                DB::table('role_permissions')->updateOrInsert([
                    'role_id'=>$roleId,'permission_id'=>$permissionId
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids=DB::table('permissions')->whereIn('name',[
            'sales.fulfillment.view','sales.fulfillment.create',
            'sales.fulfillment.pick','sales.fulfillment.pack',
        ])->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};
