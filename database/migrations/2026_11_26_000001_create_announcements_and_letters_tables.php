<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۲: اعلان‌ها و مکاتیب رسمی (صادره و وارده)، قالب‌های مکتوب و ضمیمه‌ها.
 * مکتوب حذف نمی‌شود؛ باطل می‌شود (status = cancelled با دلیل). شماره برای هر جهت در هر سال شمسی پشت‌سرهم است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('body');
            $table->string('audience', 20)->default('all');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_pinned')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedInteger('attachment_size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('type', 40)->default('general');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 20);                    // outgoing | incoming
            $table->string('status', 20)->default('draft');     // draft | issued | cancelled
            $table->unsignedSmallInteger('year')->nullable();   // سال شمسی شماره
            $table->unsignedInteger('serial')->nullable();      // پشت‌سرهم در همان سال و جهت
            $table->string('number', 60)->nullable();           // شماره‌ی کامل با پیشوند (ارقام لاتین)
            $table->string('type', 40)->default('general');
            $table->date('letter_date');
            $table->foreignId('letter_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 200)->nullable();       // صادره: گیرنده؛ وارده: فرستنده
            $table->string('organization', 200)->nullable();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('external_number', 100)->nullable(); // وارده: شماره‌ی مکتوب فرستنده
            $table->date('received_on')->nullable();            // وارده: تاریخ رسیدن
            $table->boolean('needs_response')->default(false);  // وارده: جواب لازم دارد
            $table->foreignId('reply_to_id')->nullable()->constrained('letters')->nullOnDelete(); // صادره: جواب کدام وارده

            $table->dateTime('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['direction', 'year', 'serial']);
            $table->index(['direction', 'letter_date']);
            $table->index('number');
        });

        Schema::create('letter_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('letter_attachments');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_templates');
        Schema::dropIfExists('announcements');
    }
};
