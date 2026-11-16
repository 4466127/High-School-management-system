<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۸ — کورس‌های تقویتی و کمک‌های مردمی (جدا از سال تحصیلی و مالی مکتب).
 *
 * courses              — کورس با دوره‌ی زمانی خودش (زمستانی، در جریان سال، تابستانی)
 * course_groups        — گروه/صنف کورس با نوبت (قبل/بعد از ظهر)، معلم و زمان درس
 * course_participants  — دانش‌آموز مکتب (student_id) یا فرد بیرونی (نام، نام پدر، تلفن)
 * course_sessions / course_attendance — حاضری هر گروه در هر روز
 * course_ledger_entries — دفتر مالی جدای هر کورس: کمک (به صندوق یا مستقیم به معلم)، معاش معلم، مصرف. باطل می‌شود، حذف نه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('field', 100);                         // رشته: کامپیوتر، انگلیسی، ریاضی، …
            $table->string('season', 20);                         // winter | school_year | summer
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('planned');    // planned | active | finished | cancelled
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });

        Schema::create('course_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('shift', 20);                          // morning | afternoon
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('schedule', 150)->nullable();          // روزها و ساعت درس
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'name']);
        });

        Schema::create('course_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150)->nullable();              // فقط فرد بیرونی
            $table->string('father_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('joined_on');
            $table->string('status', 20)->default('active');     // active | left | completed
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['course_group_id', 'student_id']);
        });

        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_group_id', 'date']);
        });

        Schema::create('course_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_participant_id')->constrained()->restrictOnDelete();
            $table->string('status', 10);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['course_session_id', 'course_participant_id'], 'course_attendance_unique');
        });

        Schema::create('course_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 20);                     // donation | teacher_salary | expense
            $table->string('channel', 20)->default('cashbox');   // cashbox | direct_to_teacher (فقط کمک)
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete(); // معلم (معاش و کمک مستقیم)
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('donor_name', 150)->nullable();        // خالی = بی‌نام
            $table->string('description', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['course_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_ledger_entries');
        Schema::dropIfExists('course_attendance');
        Schema::dropIfExists('course_sessions');
        Schema::dropIfExists('course_participants');
        Schema::dropIfExists('course_groups');
        Schema::dropIfExists('courses');
    }
};
