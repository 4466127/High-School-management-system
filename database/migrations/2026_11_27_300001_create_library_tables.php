<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * مرحله‌ی ۱۷: کتابخانه.
 * books = عنوان (فهرست کتاب‌ها)؛ book_copies = هر جلد فزیکی با «نمبر ثبت» یکتا (دفتر ثبت کتابخانه)؛
 * book_loans = امانت هر جلد به دانش‌آموز یا کارمند (امانت عادی یا توزیع کتاب درسی سال).
 * جلد و امانت حذف نمی‌شوند: جلد «خارج از استفاده/خراب/گم» می‌شود، امانت «بازگشت/گم». فقط افزودنی.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('books')) {
            return;
        }

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->index();
            $table->string('author', 150)->nullable()->index();
            $table->string('translator', 150)->nullable();
            $table->string('publisher', 150)->nullable();
            $table->unsignedSmallInteger('publish_year')->nullable();   // همان‌طور که روی کتاب است (شمسی یا میلادی)
            $table->string('edition', 50)->nullable();
            $table->string('isbn', 20)->nullable()->index();
            $table->string('category', 30)->index();                    // App\Enums\BookCategory
            $table->string('language', 20)->default('dari');            // App\Enums\BookLanguage
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_number', 30)->nullable();              // جای کتاب: الماری/ردیف یا کود صنف‌بندی
            $table->unsignedSmallInteger('pages')->nullable();
            $table->string('keywords', 300)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_reference')->default(false);            // فقط مطالعه در کتابخانه؛ امانت داده نمی‌شود
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->string('accession_no', 30)->unique();               // نمبر ثبت (دفتر ثبت کتابخانه)
            $table->string('status', 20)->default('available')->index(); // available | on_loan | damaged | lost | withdrawn
            $table->string('condition', 20)->default('good');           // new | good | fair | poor
            $table->string('source', 20)->default('purchase');          // purchase | donation | ministry | other
            $table->date('acquired_on')->nullable();
            $table->decimal('price', 12, 2)->nullable();                // افغانی
            $table->string('notes', 300)->nullable();
            $table->string('status_reason', 300)->nullable();           // دلیل خراب/گم/خارج
            $table->date('status_changed_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_copy_id')->constrained()->restrictOnDelete();
            $table->string('borrower_type', 10);                        // student | staff
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('loan');                // loan | textbook
            $table->date('loaned_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->unsignedTinyInteger('renewals')->default(0);
            $table->string('status', 20)->default('active');            // active | returned | lost
            $table->string('return_condition', 20)->nullable();         // new | good | fair | poor | damaged
            $table->string('notes', 300)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_on']);
            $table->index(['student_id', 'status']);
            $table->index(['staff_id', 'status']);
            $table->index(['book_copy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_loans');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('books');
    }
};
