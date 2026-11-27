<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۸: لابراتوار.
 * labs = هر لابراتوار (کیمیا، فزیک، بیولوژی، کمپیوتر …)؛ lab_items = موجودی (تجهیزات، ظروف، مواد کیمیاوی، مواد مصرفی)؛
 * lab_item_events = دفتر ورود و خروج هر قلم (رسید، مصرف، خراب، ترمیم، گم، دور انداختن، شمارش) — حذف نمی‌شود؛
 * practical_activities = فعالیت عملی (تجربه) یک بخش در لابراتوار؛ practical_activity_student = حاضری و نمره‌ی اختیاری شاگردان.
 * مقدار (quantity) عدد اعشاری دو رقمی است (گرام، ملی‌لیتر)؛ قیمت DECIMAL(12,2) افغانی. فقط افزودنی.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('labs')) {
            return;
        }

        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('type', 20);                                 // App\Enums\LabType
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();   // مسئول
            $table->text('safety_notes')->nullable();                   // قاعده‌های ایمنی (روی چاپ فعالیت)
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('kind', 20);                                 // App\Enums\LabItemKind
            $table->string('code', 30)->nullable()->unique();           // کود دارایی / نمبر ثبت
            $table->string('unit', 20)->default('piece');               // App\Enums\LabUnit
            $table->decimal('quantity', 12, 2)->default(0);             // قابل استفاده
            $table->decimal('damaged_quantity', 12, 2)->default(0);     // خراب، منتظر ترمیم
            $table->decimal('min_quantity', 12, 2)->nullable();         // کمتر از این ← هشدار کمبود
            $table->string('location', 50)->nullable();                 // الماری / قفسه
            $table->string('hazard', 20)->nullable();                   // App\Enums\ChemicalHazard (مواد کیمیاوی)
            $table->date('expires_on')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('source', 20)->default('purchase');          // App\Enums\CopySource
            $table->string('specification', 300)->nullable();           // مشخصات، فورمول، غلظت
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lab_id', 'kind']);
            $table->index('name');
        });

        Schema::create('practical_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();
            $table->date('activity_date');
            $table->string('title', 200);
            $table->string('objectives', 1000)->nullable();
            $table->string('materials_note', 1000)->nullable();         // وسایل و مواد لازم (پلان)
            $table->string('status', 20)->default('planned');           // planned | done | cancelled
            $table->unsignedTinyInteger('max_score')->nullable();       // نمره‌ی عملی (اختیاری)
            $table->string('result_note', 1000)->nullable();            // مشاهده و نتیجه
            $table->string('cancel_reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lab_id', 'activity_date']);
            $table->index(['section_id', 'activity_date']);
        });

        Schema::create('lab_item_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_item_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);                                 // App\Enums\LabEventType
            $table->decimal('quantity', 12, 2);                         // اندازه‌ی رویداد (مثبت؛ «شمارش» با علامت)
            $table->decimal('balance_after', 12, 2);
            $table->date('event_date');
            $table->foreignId('practical_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['lab_item_id', 'event_date']);
        });

        Schema::create('practical_activity_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practical_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->boolean('present')->default(true);
            $table->decimal('score', 5, 2)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['practical_activity_id', 'enrollment_id'], 'pa_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practical_activity_student');
        Schema::dropIfExists('lab_item_events');
        Schema::dropIfExists('practical_activities');
        Schema::dropIfExists('lab_items');
        Schema::dropIfExists('labs');
    }
};
