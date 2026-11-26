<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۵: تقسیم اوقات — ساعت‌های درسی (برای هر نوبت)، اتاق‌ها، و خانه‌های تقسیم اوقات هر بخش.
 * کلیدهای یکتا تداخل را در خود پایگاه داده هم می‌بندند: یک بخش در یک ساعت یک درس؛
 * یک معلم یا یک اتاق در یک سال، روز و ساعت فقط یک جا (staff_id/room_id خالی تداخل حساب نمی‌شود).
 * فقط افزودنی.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_entries')) {
            return;
        }

        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);                     // ساعت اول، تفریح، …
            $table->string('shift', 20)->default('morning'); // morning | afternoon
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_break')->default(false);    // تفریح: درس ندارد
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shift', 'sort_order']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('type', 30)->default('classroom'); // RoomType
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('notes', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('day', 10);                      // saturday … thursday
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'day', 'period_id'], 'tt_section_slot');
            $table->unique(['academic_year_id', 'staff_id', 'day', 'period_id'], 'tt_teacher_slot');
            $table->unique(['academic_year_id', 'room_id', 'day', 'period_id'], 'tt_room_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('periods');
    }
};
