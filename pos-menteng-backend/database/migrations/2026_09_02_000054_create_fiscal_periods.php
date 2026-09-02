<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('fiscal_periods',function(Blueprint $t){
   $t->id();
   $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
   $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
   $t->unsignedSmallInteger('year');
   $t->unsignedTinyInteger('month');
   $t->date('starts_on');
   $t->date('ends_on');
   $t->string('status',20)->default('open')->index();
   $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamp('closed_at')->nullable();
   $t->text('notes')->nullable();
   $t->timestamps();
   $t->unique(['tenant_id','company_id','year','month']);
  });
 }
 public function down():void{Schema::dropIfExists('fiscal_periods');}
};
