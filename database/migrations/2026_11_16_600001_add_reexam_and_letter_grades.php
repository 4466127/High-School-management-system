<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * امتحان مشروطی (دوباره) و درجه‌ی حرفی کارنامه (الف، ب، ج، د).
 *
 * reexam_marks — نمره‌ی مشروطی یک دانش‌آموز «مشروط» در یک مضمون ناکام (از ۱۰۰). تغییرها فقط از راه ReexamService.
 * enrollments.final_grade — درجه‌ی حرفی (a, b, c, d) هنگام نهایی‌کردن؛ class_rank دیگر نوشته نمی‌شود (درجه‌ی عددی در مکاتب نیست).
 * enrollments.reexam_finalized_at — نتیجه‌ی مشروطی نهایی شد (نتیجه‌ی تازه در final_result و نتیجه‌ی اصلی در final_snapshot.reexam).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reexam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('mark', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);         // غایب در مشروطی ← صفر
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'subject_id']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('final_grade', 5)->nullable()->after('class_rank');
            $table->timestamp('reexam_finalized_at')->nullable()->after('finalized_by');
            $table->foreignId('reexam_finalized_by')->nullable()->after('reexam_finalized_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reexam_finalized_by');
            $table->dropColumn(['final_grade', 'reexam_finalized_at']);
        });
        Schema::dropIfExists('reexam_marks');
    }
};
