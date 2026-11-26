<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۴: نتیجه‌ی سال‌هایی که در سیستم نیست (درج دستی) — فارغ‌های سال‌های پیش از سیستم،
 * یا سال‌هایی که دانش‌آموز در مکتب دیگر خوانده است. برای ترانسکریپت (صنوف ۱۰ تا ۱۲) و سوابق تحصیلی.
 * فقط افزودنی. سطر حذف نمی‌شود؛ اشتباه «باطل» می‌شود (cancelled_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_past_results')) {
            return;
        }

        Schema::create('student_past_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete(); // برای جای ستون در ترانسکریپت
            $table->string('grade_name', 100);                  // نام صنف هنگام ثبت (اگر صنف بعداً عوض شود)
            $table->string('year_name', 20);                    // سال شمسی، مثلاً 1402
            $table->string('school_name', 200)->nullable();     // خالی = همین مکتب
            $table->decimal('total', 8, 2)->nullable();
            $table->decimal('average', 5, 2)->nullable();
            $table->string('result', 20)->nullable();           // FinalResult
            $table->string('grade_letter', 5)->nullable();      // a | b | c | d
            $table->json('subjects')->nullable();               // [{name, mark, passed}]
            $table->string('note', 500)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_past_results');
    }
};
