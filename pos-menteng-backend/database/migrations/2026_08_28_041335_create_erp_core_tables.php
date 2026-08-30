<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->date('tanggal_lahir')->nullable();
                $table->string('WA');
                $table->string('position');
                $table->date('join_date')->nullable();
                $table->decimal('base_sallary', 12, 2)->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        // 1. MODUL CRM (Manajemen Pelanggan & Loyalitas)
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->unique()->nullable();
                $table->integer('points')->default(0);
                $table->enum('tier', ['silver', 'gold', 'vip'])->default('silver');
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('orders', 'customer_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        // 2. MODUL HRIS (Absensi & Penggajian)
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('tanggal');
                $table->dateTime('clock_in');
                $table->dateTime('clock_out')->nullable();
                $table->string('status')->default('hadir');
                $table->integer('late_minute')->default(0);
                $table->timestamps();

                $table->unique(['employee_id', 'tanggal']);
            });
        }

        if (!Schema::hasTable('payrolls')) {
            Schema::create('payrolls', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('period');
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->decimal('allowance', 12, 2)->default(0);
                $table->decimal('deduction', 12, 2)->default(0);
                $table->decimal('total_salary', 12, 2);
                $table->boolean('is_paid')->default(false);
                $table->timestamps();
            });
        }

        // 3. MODUL AKUNTANSI (Buku Besar & Jurnal)
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->date('date');
                $table->string('description');
                $table->decimal('debit', 12, 2)->default(0);
                $table->decimal('credit', 12, 2)->default(0);
                $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignUuid('operational_expense_id')->nullable()->constrained('operational_expenses')->nullOnDelete();
                $table->foreignId('restock_history_id')->nullable()->constrained('restock_histories')->nullOnDelete();

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('attendances');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::dropIfExists('customers');
    }
};