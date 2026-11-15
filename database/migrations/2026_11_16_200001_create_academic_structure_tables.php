<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ساختار آموزشی: سال تحصیلی ← دوره‌ها؛ صنف ← بخش (هر سال جدا) ← دانش‌آموزان (enrollments)؛
 * مضامین هر صنف؛ معلم هر مضمون در هر بخش.
 * enrollments: یک سطر برای هر دانش‌آموز در هر سال؛ سطرهای سال‌های قبل با ارتقا تغییر نمی‌کنند
 * (فقط status آن‌ها promoted/repeated/graduated می‌شود).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();              // مثلاً ۱۴۰۵
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 10)->default('open');      // open | closed
            $table->timestamps();
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                        // چهارونیم‌ماهه‌ی اول …
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();               // صنف اول …
            $table->unsignedSmallInteger('sort_order')->unique(); // ترتیب؛ صنف بعدی برای ارتقا
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('grade_level_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1); // ترتیب در کارنامه
            $table->unique(['grade_level_id', 'subject_id']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->string('name', 20);                          // الف، ب، ج
            $table->string('shift', 12)->default('morning');      // App\Enums\Shift
            $table->foreignId('class_teacher_id')->nullable()->constrained('staff')->nullOnDelete(); // نگران صنف
            $table->string('room', 50)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_level_id', 'name', 'shift']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'subject_id']);
            $table->index('staff_id');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('roll_no')->nullable();  // نمبر حاضری
            $table->date('enrolled_on')->nullable();
            $table->string('status', 15)->default('active');      // App\Enums\EnrollmentStatus
            $table->date('left_on')->nullable();
            $table->string('leave_reason', 500)->nullable();
            // نتیجه‌ی نهایی سال (مرحله‌ی ۶)
            $table->decimal('final_total', 8, 2)->nullable();
            $table->decimal('final_average', 5, 2)->nullable();
            $table->string('final_result', 15)->nullable();
            $table->unsignedSmallInteger('class_rank')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['section_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('grade_level_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_years');
    }
};
