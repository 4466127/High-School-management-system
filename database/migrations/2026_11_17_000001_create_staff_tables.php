<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * معلمان و همه‌ی کارکنان در یک جدول (DATABASE.md). حذف نرم؛ در عمل کارمند «رفته» می‌شود، نه حذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_type', 20)->index();          // App\Enums\StaffType
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('father_name', 100)->nullable();
            $table->string('gender', 10);                       // App\Enums\Gender
            $table->string('phone', 30)->nullable();
            $table->string('education', 150)->nullable();
            $table->string('specialization', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('position', 150)->nullable();        // وظیفه
            $table->string('employment_status', 20)->default('active')->index(); // App\Enums\EmploymentStatus
            $table->date('status_changed_on')->nullable();
            $table->string('status_reason', 500)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('staff_work_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('organization', 150)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->unique()->after('username')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::dropIfExists('staff_work_history');
        Schema::dropIfExists('staff');
    }
};
