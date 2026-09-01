<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('customer_payments',function(Blueprint $t){
   $t->uuid('id')->primary();
   $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
   $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
   $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
   $t->foreignUuid('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
   $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
   $t->string('customer_name_snapshot')->nullable();
   $t->string('payment_number',80)->unique();
   $t->date('payment_date');
   $t->decimal('amount',18,2);
   $t->string('method',30);
   $t->string('reference')->nullable();
   $t->foreignId('paid_by')->constrained('users')->cascadeOnDelete();
   $t->uuid('request_id')->nullable()->index();
   $t->text('notes')->nullable();
   $t->timestamps();
   $t->unique(['tenant_id','request_id']);
   $t->index(['tenant_id','company_id','branch_id','payment_date']);
  });
 }
 public function down():void{Schema::dropIfExists('customer_payments');}
};
