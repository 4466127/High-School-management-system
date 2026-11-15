<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * دانش‌آموزان، سرپرستان (مشترک بین خواهر و برادرها) و اسناد.
 * صنف و بخش دانش‌آموز اینجا نیست؛ در جدول enrollments مرحله‌ی ۴ (یک سطر برای هر سال تحصیلی).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_no', 30)->unique();       // نمبر اساس
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('father_name', 100);
            $table->string('grandfather_name', 100)->nullable();
            $table->string('gender', 10);                       // App\Enums\Gender
            $table->date('birth_date')->nullable();
            $table->string('tazkira_no', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('province', 50)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('village', 150)->nullable();         // قریه / محل
            $table->string('address', 500)->nullable();
            $table->date('admission_date')->nullable();
            $table->string('status', 20)->default('active')->index(); // App\Enums\StudentStatus
            $table->date('status_changed_on')->nullable();
            $table->string('status_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('deleted_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('father_name');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable()->index();
            $table->string('phone2', 30)->nullable();
            $table->string('occupation', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 50);                     // پدر، مادر، کاکا …
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
