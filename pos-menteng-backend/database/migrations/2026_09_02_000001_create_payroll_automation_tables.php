<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_automation_config')) {
            Schema::create('payroll_automation_config', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->boolean('enable_auto_fill')->default(true);
                $table->boolean('enable_whatsapp_notification')->default(true);
                $table->boolean('whatsapp_recipient_employee')->default(true);
                $table->boolean('whatsapp_recipient_manager')->default(true);
                $table->string('manager_phone', 32)->nullable();
                $table->string('notification_timing', 50)->default('immediate');
                $table->text('message_template')->default('Slip gaji periode {period} untuk {employee_name} terlampir.');
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }

        if (! Schema::hasTable('payroll_notifications')) {
            Schema::create('payroll_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->string('recipient_type', 50);
                $table->string('recipient_phone', 32)->nullable();
                $table->text('message_content')->nullable();
                $table->string('pdf_file_path')->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('provider_message_id', 120)->nullable();
                $table->string('provider_status', 50)->nullable();
                $table->string('status', 50)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['payroll_id', 'recipient_type']);
                $table->index(['status', 'provider_status']);
                $table->unique(['payroll_id', 'recipient_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_notifications');
        Schema::dropIfExists('payroll_automation_config');
    }
};
