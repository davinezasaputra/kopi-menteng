<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions=[
            ['module'=>'sales','resource'=>'shipment','action'=>'view'],
            ['module'=>'sales','resource'=>'shipment','action'=>'create'],
        ];

        foreach($permissions as $permission){
            $name=implode('.',[$permission['module'],$permission['resource'],$permission['action']]);
            DB::table('permissions')->updateOrInsert(
                ['name'=>$name],
                $permission+['created_at'=>now(),'updated_at'=>now()]
            );
        }

        $ids=DB::table('permissions')->whereIn('name',[
            'sales.shipment.view','sales.shipment.create',
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
            'sales.shipment.view','sales.shipment.create',
        ])->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};
