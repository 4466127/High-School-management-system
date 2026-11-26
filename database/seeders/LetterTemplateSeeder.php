<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;

/**
 * چند قالب ساده‌ی آغازین (مرحله‌ی ۱۲). این‌ها شکل رسمی وزارت معارف نیستند؛ فقط نمونه‌اند تا مکتب
 * متن خودش را جایگزین کند. قالبی که هست (هم‌نام) هرگز عوض نمی‌شود.
 */
class LetterTemplateSeeder extends Seeder
{
    public const TEMPLATES = [
        [
            'name' => 'مکتوب عمومی',
            'type' => 'general',
            'subject' => '',
            'body' => "محترما!\n\n\n\nبا احترام",
        ],
        [
            'name' => 'تصدیق شمولیت دانش‌آموز',
            'type' => 'enrollment_confirmation',
            'subject' => 'تصدیق شمولیت {student_name}',
            'body' => "محترما!\n\nبه اطلاع می‌رساند که {student_name} فرزند {father_name}، به نمبر اساس {admission_no}، در سال تحصیلی {academic_year} در {class} {school_name} مشغول تحصیل است.\n\nاین تصدیق به درخواست خود او صادر شد.\n\nبا احترام",
        ],
        [
            'name' => 'معرفی‌نامه‌ی دانش‌آموز',
            'type' => 'student_introduction',
            'subject' => 'معرفی {student_name}',
            'body' => "محترما!\n\n{student_name} فرزند {father_name}، دانش‌آموز {class} این مکتب، به حضور معرفی می‌شود تا ...\n\nبا احترام",
        ],
        [
            'name' => 'تصدیق کار کارمند',
            'type' => 'employment_confirmation',
            'subject' => 'تصدیق کار {staff_name}',
            'body' => "محترما!\n\nبه اطلاع می‌رساند که {staff_name} فرزند {staff_father_name} از تاریخ {hire_date} به حیث {staff_position} در {school_name} وظیفه اجرا می‌کند.\n\nاین تصدیق به درخواست خود او صادر شد.\n\nبا احترام",
        ],
        [
            'name' => 'دعوت‌نامه',
            'type' => 'invitation',
            'subject' => 'دعوت‌نامه',
            'body' => "محترما!\n\nبه این وسیله از شما دعوت می‌شود تا در ... که به تاریخ ... ساعت ... در {school_name} برگزار می‌شود، اشتراک نمایید.\n\nبا احترام",
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            LetterTemplate::firstOrCreate(['name' => $template['name']], $template + ['is_active' => true]);
        }
    }
}
