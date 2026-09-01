<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration{
 public function up():void{
  $m=DB::table('tenants')->pluck('id'); $companies=DB::table('companies')->get(['id','tenant_id']);
  foreach($companies as $c){
   DB::table('erp_accounts')->updateOrInsert(['tenant_id'=>$c->tenant_id,'company_id'=>$c->id,'code'=>'1200'],['name'=>'Accounts Receivable','type'=>'asset','normal_balance'=>'debit','is_postable'=>true,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
  }
 }
 public function down():void{DB::table('erp_accounts')->where('code','1200')->delete();}
};
