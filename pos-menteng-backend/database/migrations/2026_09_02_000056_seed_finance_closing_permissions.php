<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration{
 public function up():void{
  $defs=[
   ['accounting','fiscal_period','view'],['accounting','fiscal_period','manage'],
   ['accounting','report','view'],['accounting','reconciliation','view'],['accounting','reconciliation','create'],
   ['accounting','period','close'],
  ];
  foreach($defs as [$module,$resource,$action]){
   $name="$module.$resource.$action";
   DB::table('permissions')->updateOrInsert(['name'=>$name],['module'=>$module,'resource'=>$resource,'action'=>$action,'created_at'=>now(),'updated_at'=>now()]);
  }
  $ids=DB::table('permissions')->whereIn('name',array_map(fn($x)=>implode('.',$x),$defs))->pluck('id');
  $roles=DB::table('roles')->whereIn('code',['tenant-admin','accountant'])->pluck('id');
  foreach($roles as $roleId)foreach($ids as $permissionId)DB::table('role_permissions')->updateOrInsert(['role_id'=>$roleId,'permission_id'=>$permissionId]);
 }
 public function down():void{
  $names=['accounting.fiscal_period.view','accounting.fiscal_period.manage','accounting.report.view','accounting.reconciliation.view','accounting.reconciliation.create','accounting.period.close'];
  $ids=DB::table('permissions')->whereIn('name',$names)->pluck('id');
  DB::table('role_permissions')->whereIn('permission_id',$ids)->delete(); DB::table('permissions')->whereIn('id',$ids)->delete();
 }
};
