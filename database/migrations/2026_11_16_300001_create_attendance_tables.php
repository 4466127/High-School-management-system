<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * حاضری روزانه:
 * attendance_sessions — یک «ورق حاضری» برای هر بخش در هر روز (کلید یکتا ← ثبت دوباره ممکن نیست).
 * attendance_records  — وضعیت هر دانش‌آموز در آن ورق؛ به enrollment وصل است، نه student (تاریخچه‌ی سال‌ها می‌ماند).
 * staff_attendance    — حاضری ساده‌ی کارکنان، یک سطر برای هر کارمند در هر روز.
 * هیچ‌کدام حذف نمی‌شوند؛ اصلاح با مقدار قبلی در activity_logs ثبت می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'date']);
            $table->index('date');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('status', 10);                         // App\Enums\AttendanceStatus
            $table->string('note', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'enrollment_id']);
            $table->index(['enrollment_id', 'status']);
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 10);                         // App\Enums\AttendanceStatus
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
