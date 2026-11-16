<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * امتحانات و نمرات:
 * exam_types — نوع امتحان با نمره‌ی کامل و وزن پیش‌فرض (چهارونیم‌ماهه ۴۰، سالانه ۶۰، …).
 * exams      — امتحان‌های هر سال؛ نمره‌ی کامل و وزن هنگام ساخت از نوع کاپی می‌شود (تغییر نوع، امتحان‌های قبلی را عوض نمی‌کند).
 * marks      — نمره‌ی هر دانش‌آموز (از راه شمولیت) در هر مضمون در هر امتحان. حذف نمی‌شود.
 * enrollments.final_snapshot — نسخه‌ی ثابت نتیجه‌ی نهایی (نمرات، قاعده‌ها و حاضری هنگام نهایی‌کردن) برای کارنامه‌ی سال بسته.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('default_max_mark', 5, 2);
            $table->decimal('default_weight', 5, 2)->default(0);
            $table->boolean('counts_in_final')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('max_mark', 5, 2);
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('counts_in_final')->default(true);
            $table->string('status', 10)->default('draft');     // App\Enums\ExamStatus
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('mark', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);         // غایب در امتحان ← صفر
            $table->string('note', 255)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'enrollment_id', 'subject_id']);
            $table->index(['enrollment_id', 'exam_id']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->json('final_snapshot')->nullable()->after('finalized_by');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('final_snapshot');
        });
        Schema::dropIfExists('marks');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_types');
    }
};
