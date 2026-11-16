<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مالی مکتب (اختیاری؛ با تنظیمات fees_enabled و school_ledger_enabled):
 * fee_types / fee_rates       — انواع فیس و نرخ هر صنف در هر سال.
 * student_invoices            — بل هر دانش‌آموز (از راه شمولیت). یک بل فعال برای هر شمولیت، نوع و دوره (active_key).
 * payments / payment_allocations — رسید با شماره‌ی پشت‌سرهم؛ هر رسید میان یک یا چند بل تقسیم می‌شود.
 * finance_categories / school_transactions — درآمد و مصرف عمومی مکتب.
 * پول DECIMAL(12,2) افغانی. هیچ سطر مالی حذف نمی‌شود؛ «باطل» می‌شود (voided_at، voided_by، void_reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('period', 10)->default('monthly');   // monthly | annual | once
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['fee_type_id', 'academic_year_id', 'grade_level_id']);
        });

        Schema::create('student_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->string('period_label', 50);                     // «حمل ۱۴۰۵»، «سال ۱۴۰۵»
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            // ۱ برای بل فعال، NULL برای باطل‌شده ← کلید یکتا فقط بل‌های فعال را محدود می‌کند
            $table->boolean('active_key')->nullable()->storedAs('IF(`voided_at` IS NULL, 1, NULL)');
            $table->timestamps();

            $table->unique(['enrollment_id', 'fee_type_id', 'period_label', 'active_key'], 'student_invoices_active_unique');
            $table->index(['student_id', 'voided_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('receipt_no')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('payer_name', 150)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index('paid_on');
            $table->index(['student_id', 'voided_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('student_invoice_id');
        });

        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                              // income | expense
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'name']);
        });

        Schema::create('school_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                              // income | expense
            $table->foreignId('finance_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('party_name', 150)->nullable();           // کمک‌کننده یا گیرنده
            $table->string('description', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_transactions');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('student_invoices');
        Schema::dropIfExists('fee_rates');
        Schema::dropIfExists('fee_types');
    }
};
