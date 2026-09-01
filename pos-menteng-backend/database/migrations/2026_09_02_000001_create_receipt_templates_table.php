<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('business_name', 120)->default('KOPI MENTENG');
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->enum('paper_width', ['58mm', '80mm'])->default('80mm');
            $table->boolean('show_cashier')->default(true);
            $table->boolean('show_customer')->default(true);
            $table->boolean('show_order_type')->default(true);
            $table->boolean('show_tax')->default(true);
            $table->boolean('show_discount')->default(true);
            $table->boolean('show_sku')->default(false);
            $table->boolean('show_change')->default(true);
            $table->text('footer_text')->nullable();
            $table->text('wifi_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id', 'branch_id']);
            $table->index(['tenant_id', 'company_id', 'branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
