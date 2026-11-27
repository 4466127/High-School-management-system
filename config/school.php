<?php

/*
|--------------------------------------------------------------------------
| مقدارهای پیش‌فرض تنظیمات مکتب
|--------------------------------------------------------------------------
| SettingsSeeder این مقدارها را فقط وقتی می‌نویسد که کلید هنوز در جدول settings نباشد؛
| پس تنظیمات مکتب هرگز با اجرای دوباره‌ی seeder عوض نمی‌شود.
| همه از صفحه‌ی تنظیمات قابل تغییر است؛ در کد هیچ‌کدام ثابت نیست.
*/

return [
    /*
    | پشتیبان (مرحله‌ی ۱۰؛ BackupService). پشتیبان خودکار ماهانه: دکمه‌ی دسکتاپ (tools\start.bat)
    | هر بار «php artisan school:backup --if-due» را اجرا می‌کند؛ اگر آخرین پشتیبان کهنه‌تر از every_days باشد، تازه می‌گیرد.
    | keep = شمار نسخه‌های نگه‌داشته از هر نوع (خودکار، دستی، پیش از بازیابی، پیش از پاک‌کردن).
    | copy_to = پوشه‌ی دوم (مثلاً فلش E:\maktab-backup)؛ اگر وصل باشد، یک کاپی هم آن‌جا می‌رود.
    */
    'backup' => [
        'path' => env('BACKUP_PATH', base_path('backups')),
        'keep' => (int) env('BACKUP_KEEP', 12),
        'every_days' => (int) env('BACKUP_EVERY_DAYS', 30),
        'copy_to' => env('BACKUP_COPY_TO', ''),
        'mysql_bin' => env('MYSQL_BIN', 'C:\\xampp\\mysql\\bin'),
    ],

    'defaults' => [
        // مشخصات مکتب
        'school_name' => 'مکتب',
        'school_type' => 'government',      // government | private
        'province' => '',
        'district' => '',
        'address' => '',
        'phone' => '',
        'principal_name' => '',
        'logo_path' => '',
        // سربرگ رسمی (مرحله‌ی ۱۱): سطرهای بالای نام مکتب در چاپ‌ها و مکاتیب. هیچ متن رسمی پیش‌فرض نیست؛
        // هر مکتب سطرهای خودش را می‌نویسد (مثلاً نام وزارت و ریاست معارف ولایت).
        'header_line' => '',
        'directorate' => '',
        'school_code' => '',
        'email' => '',
        // مکاتیب (مرحله‌ی ۱۲): پیشوند شماره، عنوان جای امضاها، جای مهر. همه از «مشخصات مکتب» قابل تغییر
        'letter_outgoing_prefix' => '',
        'letter_incoming_prefix' => '',
        'letter_signature_1' => 'مدیر مکتب',
        'letter_signature_2' => '',
        'letter_show_stamp' => '1',
        // قالب کارنامه و ترانسکریپت (مرحله‌ی ۱۴؛ «امتحانات ← قالب کارنامه و ترانسکریپت»). عنوان خالی = عنوان پیش‌فرض.
        // شکل رسمی وزارت معارف حدس زده نشده؛ هر مکتب با نمونه‌ی خودش تنظیم می‌کند.
        'reportcard_title' => '',
        'reportcard_subtitle' => '',
        'reportcard_show_exams' => '1',         // ستون نمره‌ی هر امتحان (چهارونیم‌ماهه، سالانه)
        'reportcard_show_attendance' => '1',
        'reportcard_show_grade' => '1',
        'reportcard_show_rules' => '1',         // سطر توضیح حد کامیابی و درجه‌ها
        'reportcard_show_remarks' => '0',       // خانه‌ی خالی «ملاحظات» برای نوشتن با دست
        'reportcard_signature_1' => 'امضای نگران صنف',
        'reportcard_signature_2' => 'امضای مدیر مکتب',
        'reportcard_signature_3' => '',
        'reportcard_show_stamp' => '0',
        'reportcard_footer' => '',
        'transcript_title' => '',
        'transcript_grade_ids' => '',           // خالی = سه صنف آخر فعال (دهم، یازدهم، دوازدهم)
        'transcript_signature_1' => 'امضای مدیر مکتب',
        'transcript_signature_2' => '',
        'transcript_show_stamp' => '1',
        'transcript_footer' => '',
        // کتابخانه (مرحله‌ی ۱۷؛ LibraryService::rules): مدت امانت (روز)، حد امانت هم‌زمان، شمار تمدید،
        // و این‌که امانت‌گیرنده‌ی دیرکرده کتاب تازه بگیرد یا نه. کتاب درسی سال در حد امانت حساب نمی‌شود.
        'library_loan_days_student' => '14',
        'library_loan_days_staff' => '30',
        'library_max_student' => '2',
        'library_max_staff' => '5',
        'library_max_renewals' => '1',
        'library_block_overdue' => '1',
        'library_name' => '',                   // نام کتابخانه روی چاپ‌ها؛ خالی = «کتابخانه‌ی <نام مکتب>»

        // شناسه‌ی این نصب (برای لایسنس؛ school:install یا اولین بار LicenseService می‌سازد)
        'installation_id' => '',

        // مالی (هر دو خاموش؛ مکتب خصوصی فیس را روشن می‌کند)
        'fees_enabled' => '0',
        'school_ledger_enabled' => '0',
        'currency' => 'AFN',

        // نمره‌دهی (تصمیم ۲ و ۳ ROADMAP). وزن امتحان‌ها (۴۰ و ۶۰) در جدول exam_types مرحله‌ی ۶ است.
        'pass_percent' => '40',                     // حداقل فیصدی کامیابی در هر مضمون
        'conditional_max_failed_subjects' => '2',   // حداکثر مضامین ناکام برای «مشروط»؛ ۰ = غیرفعال
        'deprivation_absence_days' => '0',

        // درجه‌ی حرفی کارنامه از اوسط (فقط کامیاب‌ها): الف، ب، ج از این حدها؛ د = بقیه‌ی کامیاب‌ها
        'grade_a_min' => '90',
        'grade_b_min' => '75',
        'grade_c_min' => '60',          // غیابت بیشتر از این ← «محروم»؛ ۰ = غیرفعال

        // حاضری (مرحله‌ی ۵): حد پیش‌فرض فهرست «غیابت زیاد» (روزهای غیرحاضر بی‌اجازه در سال)
        'absence_alert_days' => '10',

        // تقویم
        'weekly_holidays' => 'friday',
    ],
];
