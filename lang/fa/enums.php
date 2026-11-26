<?php

// برچسب فارسی enumها (App\Enums\Concerns\HasLabel)
return [
    'gender' => [
        'male' => 'پسر / مرد',
        'female' => 'دختر / زن',
    ],
    'gender_student' => [
        'male' => 'پسر',
        'female' => 'دختر',
    ],
    'gender_staff' => [
        'male' => 'مرد',
        'female' => 'زن',
    ],
    'education_level' => [
        'grade_12' => 'دوازده‌پاس',
        'grade_14' => 'چهارده‌پاس',
        'bachelor' => 'کارشناسی (لیسانس)',
        'master' => 'کارشناسی ارشد (ماستری)',
        'doctorate' => 'دکترا',
    ],
    'student_status' => [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
        'transferred' => 'منتقل‌شده',
        'dropped' => 'ترک تحصیل',
        'graduated' => 'فارغ',
    ],
    'shift' => [
        'morning' => 'صبح',
        'afternoon' => 'بعدازظهر',
    ],
    'enrollment_status' => [
        'active' => 'جاری',
        'promoted' => 'ارتقا',
        'repeated' => 'تکرار صنف',
        'graduated' => 'فارغ',
        'transferred' => 'منتقل‌شده',
        'dropped' => 'ترک تحصیل',
    ],
    'school_type' => [
        'government' => 'دولتی',
        'private' => 'خصوصی',
    ],
    'staff_type' => [
        'teacher' => 'معلم',
        'admin' => 'کارمند اداری',
        'service' => 'کارمند خدماتی',
        'course_teacher' => 'معلم کورس',
    ],
    'attendance_status' => [
        'present' => 'حاضر',
        'late' => 'تأخیر',
        'absent' => 'غیرحاضر',
        'excused' => 'رخصت',
        'sick' => 'مریض',
    ],
    'attendance_status_code' => [
        'present' => 'ح',
        'late' => 'ت',
        'absent' => 'غ',
        'excused' => 'ر',
        'sick' => 'م',
    ],
    'exam_status' => [
        'draft' => 'پیش‌نویس',
        'open' => 'باز (ثبت نمره)',
        'locked' => 'قفل',
    ],
    'final_result' => [
        'passed' => 'کامیاب',
        'conditional' => 'مشروط',
        'failed' => 'ناکام',
        'deprived' => 'محروم',
    ],
    'fee_period' => [
        'monthly' => 'ماهوار',
        'annual' => 'سالانه',
        'once' => 'یک‌بار',
    ],
    'plan_status' => [
        'draft' => 'پیش‌نویس',
        'submitted' => 'فرستاده به مدیر',
        'approved' => 'منظور شد',
        'returned' => 'برگشت برای اصلاح',
    ],
    'plan_item_status' => [
        'planned' => 'در برنامه',
        'done' => 'تدریس شد',
        'partial' => 'نیمه تدریس شد',
        'skipped' => 'رها شد',
    ],
    'room_type' => [
        'classroom' => 'صنف درسی',
        'lab' => 'لابراتوار',
        'computer' => 'صنف کمپیوتر',
        'library' => 'کتابخانه',
        'hall' => 'تالار',
        'other' => 'دیگر',
    ],
    'document_category' => [
        'registration' => 'اسناد ثبت‌نام',
        'identity' => 'تذکره / هویت',
        'photo' => 'عکس',
        'certificate' => 'شهادت‌نامه / سند',
        'report_card' => 'کارنامه',
        'transfer' => 'اسناد انتقال',
        'medical' => 'صحی',
        'other' => 'دیگر',
    ],
    'transfer_direction' => [
        'out' => 'به مکتب دیگر',
        'in' => 'از مکتب دیگر',
    ],
    'announcement_audience' => [
        'all' => 'همه',
        'staff' => 'کارکنان',
        'teachers' => 'معلمان',
        'students' => 'دانش‌آموزان',
        'parents' => 'والدین',
    ],
    'announcement_status' => [
        'draft' => 'پیش‌نویس',
        'published' => 'منتشرشده',
        'archived' => 'بایگانی',
    ],
    'letter_direction' => [
        'outgoing' => 'صادره',
        'incoming' => 'وارده',
    ],
    'letter_status' => [
        'draft' => 'پیش‌نویس',
        'issued' => 'صادر / ثبت شد',
        'cancelled' => 'باطل',
    ],
    'letter_type' => [
        'general' => 'مکتوب عمومی',
        'student_introduction' => 'معرفی‌نامه‌ی دانش‌آموز',
        'enrollment_confirmation' => 'تصدیق شمولیت',
        'transfer' => 'مکتوب انتقال',
        'recommendation' => 'توصیه‌نامه',
        'certificate' => 'تصدیق‌نامه',
        'employment_confirmation' => 'تصدیق کار کارمند',
        'invitation' => 'دعوت‌نامه',
    ],
    'transaction_type' => [
        'income' => 'درآمد',
        'expense' => 'مصرف',
    ],
    'employment_status' => [
        'active' => 'برسر وظیفه',
        'on_leave' => 'رخصتی',
        'left' => 'منفک / رفته',
    ],
    'course_season' => [
        'winter' => 'زمستانی (رخصتی مکاتب)',
        'school_year' => 'در جریان سال تحصیلی',
        'summer' => 'تابستانی',
    ],
    'course_status' => [
        'planned' => 'در برنامه',
        'active' => 'جاری',
        'finished' => 'ختم‌شده',
        'cancelled' => 'لغوشده',
    ],
    'course_entry_type' => [
        'donation' => 'کمک مردمی',
        'teacher_salary' => 'معاش معلم',
        'expense' => 'مصرف کورس',
    ],
    'course_channel' => [
        'cashbox' => 'صندوق کورس',
        'direct_to_teacher' => 'مستقیم به معلم',
    ],
    'participant_status' => [
        'active' => 'مشغول',
        'left' => 'رفته',
        'completed' => 'ختم کرده',
    ],
];
