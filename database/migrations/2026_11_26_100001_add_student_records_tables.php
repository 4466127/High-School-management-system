<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۳: دسته‌ی اسناد دانش‌آموز، مشخصات سند فراغت، و انتقال میان مکاتب (صادره و وارده).
 * فقط افزودنی؛ ستون‌های تازه nullable یا با مقدار پیش‌فرض. سوابق تحصیلی همان enrollments است و دست نمی‌خورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->string('category', 30)->default('other')->after('title');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('certificate_no', 100)->nullable()->after('status_changed_on');   // شماره‌ی سند فراغت
            $table->date('certificate_date')->nullable()->after('certificate_no');
            $table->string('certificate_note', 500)->nullable()->after('certificate_date');
        });

        Schema::create('student_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10);                        // out | in
            $table->string('status', 20)->default('completed');     // completed | cancelled
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete(); // صادره: شمولیتی که بسته شد
            $table->string('other_school', 200);                    // مکتب مقصد (صادره) یا مبدأ (وارده)
            $table->string('other_province', 50)->nullable();
            $table->string('other_district', 100)->nullable();
            $table->date('requested_on')->nullable();
            $table->date('approved_on')->nullable();
            $table->date('transfer_date');
            $table->string('reason', 500);
            $table->string('reference_number', 100)->nullable();    // شماره‌ی مکتوب انتقال
            $table->string('last_grade', 100)->nullable();          // وارده: آخرین صنف خوانده‌شده
            $table->decimal('outstanding_balance', 12, 2)->default(0); // صادره: مانده‌ی فیس هنگام انتقال
            $table->string('clearance_note', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('previous_records')->nullable();           // وارده: فایل سوابق مکتب قبلی
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transfers');
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['certificate_no', 'certificate_date', 'certificate_note']));
        Schema::table('student_documents', fn (Blueprint $table) => $table->dropColumn('category'));
    }
};
