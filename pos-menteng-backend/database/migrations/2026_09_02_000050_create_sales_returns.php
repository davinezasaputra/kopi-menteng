<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('sales_returns',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete(); $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
   $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete(); $t->foreignUuid('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete(); $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete(); $t->string('customer_name_snapshot')->nullable();
   $t->string('return_number',80)->unique(); $t->date('return_date'); $t->string('status',30)->default('posted'); $t->decimal('total_amount',18,2)->default(0); $t->foreignId('created_by')->constrained('users')->cascadeOnDelete(); $t->uuid('request_id')->nullable()->index(); $t->text('reason')->nullable(); $t->timestamps();
   $t->unique(['tenant_id','request_id']); $t->index(['tenant_id','company_id','branch_id','status']);
  });
  Schema::create('sales_return_items',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('sales_return_id')->constrained('sales_returns')->cascadeOnDelete(); $t->uuid('product_id'); $t->decimal('quantity',18,4); $t->decimal('unit_price',18,4); $t->decimal('line_total',18,2); $t->timestamps();
   $t->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
   $t->unique(['sales_return_id','product_id']);
  });
 }
 public function down():void{Schema::dropIfExists('sales_return_items');Schema::dropIfExists('sales_returns');}
};
