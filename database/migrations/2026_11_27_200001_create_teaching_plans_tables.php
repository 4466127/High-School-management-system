<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۶: پلان تدریس و پیشرفت تدریس.
 * هر پلان = یک بخش + یک مضمون در یک سال (یکتا)، با معلم؛ سطرها = موضوع‌های هفته به هفته با وضعیت پیشرفت.
 * پلان حذف نمی‌شود (پیش‌نویس ← فرستاده ← منظور یا برگشت). فقط افزودنی.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teaching_plans')) {
            return;
        }

        Schema::create('teaching_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status', 20)->default('draft');     // draft | submitted | approved | returned
            $table->string('notes', 1000)->nullable();          // یادداشت معلم (کتاب، روش)
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'subject_id'], 'teaching_plan_section_subject');
        });

        Schema::create('teaching_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('week_no')->nullable();
            $table->date('planned_from')->nullable();
            $table->date('planned_to')->nullable();
            $table->string('topic', 200);
            $table->string('objectives', 1000)->nullable();
            $table->unsignedSmallInteger('planned_hours')->nullable();
            $table->string('status', 20)->default('planned');   // planned | done | partial | skipped
            $table->date('completed_on')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->index(['teaching_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_plan_items');
        Schema::dropIfExists('teaching_plans');
    }
};
