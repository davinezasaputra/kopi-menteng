<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('cash_reconciliations',function(Blueprint $t){
   $t->id();
   $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
   $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
   $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
   $t->date('reconciliation_date');
   $t->string('account_code',50);
   $t->decimal('book_balance',18,2);
   $t->decimal('statement_balance',18,2);
   $t->decimal('adjustment_amount',18,2)->default(0);
   $t->decimal('difference',18,2);
   $t->string('status',20)->default('open')->index();
   $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
   $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamp('approved_at')->nullable();
   $t->text('notes')->nullable();
   $t->timestamps();
   $t->index(['tenant_id','company_id','branch_id','reconciliation_date']);
  });
 }
 public function down():void{Schema::dropIfExists('cash_reconciliations');}
};
