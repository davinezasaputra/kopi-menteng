<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration{
 public function up():void{
  $companies=DB::table('companies')->get(['id','tenant_id']);
  foreach($companies as $company){
   for($month=1;$month<=12;$month++){
    $start=date(sprintf('%04d-%02d-01',date('Y'),$month));
    $end=date('Y-m-t',strtotime($start));
    DB::table('fiscal_periods')->updateOrInsert(
     ['tenant_id'=>$company->tenant_id,'company_id'=>$company->id,'year'=>(int)date('Y'),'month'=>$month],
     ['starts_on'=>$start,'ends_on'=>$end,'status'=>'open','created_at'=>now(),'updated_at'=>now()]
    );
   }
  }
 }
 public function down():void{
  DB::table('fiscal_periods')->where('year',(int)date('Y'))->delete();
 }
};
