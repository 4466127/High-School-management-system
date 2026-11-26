<?php

namespace Database\Seeders;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\GradeLevel;
use App\Models\Letter;
use App\Models\StudentPastResult;
use App\Models\User;
use App\Services\LetterService;
use App\Services\PastResultService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * فقط برای توسعه و آزمایش (مرحله‌ی ۱۴): داده‌ی نمونه تا هر بخش تازه خالی نباشد و زودتر فهمیده شود —
 * چند فارغ ساختگی سال‌های ۱۴۰۰ تا ۱۴۰۴ با نتیجه‌ی صنف دهم تا دوازدهم (برای بایگانی و ترانسکریپت)،
 * دو اعلان و دو مکتوب نمونه (یکی پیش‌نویس، یکی صادرشده). همه‌ی نام‌ها ساختگی است.
 * هر بخش فقط اگر خالی باشد ساخته می‌شود (اجرای دوباره امن).
 */
class DemoRecordsSeeder extends Seeder
{
    private const GRADUATES = [
        // [نام، تخلص، نام پدر، جنسیت، سال فراغت، نمبر سند، پایه‌ی نمره]
        ['فرید', 'سلطانی', 'غلام سخی', 'male', 1402, 'F-1402-031', 82],
        ['مریم', 'احمدزی', 'محمد اکبر', 'female', 1402, 'F-1402-032', 91],
        ['نوید', 'هاشمی', 'سید جلال', 'male', 1403, 'F-1403-017', 68],
        ['شبنم', 'نوری', 'عبدالقدیر', 'female', 1403, 'F-1403-018', 77],
        ['جاوید', 'رسولی', 'محمد رسول', 'male', 1404, 'F-1404-009', 59],
        ['لیلا', 'صدیقی', 'عبدالصمد', 'female', 1404, '', 86],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoRecordsSeeder must not run in production.');
        }
        $user = User::where('username', 'demo.office')->first() ?? User::where('username', 'admin')->first();
        if (! $user) {
            return;
        }

        $this->graduates($user);
        $this->announcements($user);
        $this->letters($user);
    }

    private function graduates(User $user): void
    {
        if (StudentPastResult::exists()) {
            $this->command?->info('Past results already exist; demo graduates skipped.');

            return;
        }
        $levels = GradeLevel::with('subjects')->whereIn('sort_order', [10, 11, 12])->orderBy('sort_order')->get();
        if ($levels->count() < 3) {
            return;
        }

        mt_srand(14);
        $service = app(PastResultService::class);
        foreach (self::GRADUATES as [$first, $last, $father, $gender, $year, $certificate, $base]) {
            $results = [];
            foreach ($levels->values() as $i => $level) {
                $results[] = [
                    'grade_level_id' => $level->id,
                    'year_name' => (string) ($year - 2 + $i),
                    // نتیجه‌ی صنف دوازدهم: نمره‌ی همه‌ی مضامین؛ صنف‌های قبل: فقط اوسط (مثل سوابق کاغذی کم‌جزئیات)
                    'subjects' => $i === 2 ? $level->subjects->map(fn ($s) => ['name' => $s->name, 'mark' => max(40, min(100, $base + mt_rand(-9, 9)))])->all() : [],
                    'average' => $i === 2 ? null : max(45, min(99, $base + mt_rand(-5, 5))),
                    'note' => __('past_results.ph.certificate_note'),
                ];
            }
            $service->createFormerStudent(
                ['first_name' => $first, 'last_name' => $last, 'father_name' => $father, 'gender' => $gender, 'province' => 'کابل'],
                ['status' => 'graduated', 'left_on' => ($year + 621).'-12-15', 'reason' => 'فارغ دوره‌ی لیسه (نمونه)',
                    'certificate_no' => $certificate ?: null, 'certificate_date' => $certificate ? ($year + 622).'-04-10' : null],
                $results,
                $user,
            );
        }
        $this->command?->info('Demo graduates (1402-1404) with grade 10-12 results created.');
    }

    private function announcements(User $user): void
    {
        if (Announcement::exists()) {
            return;
        }
        Announcement::create([
            'title' => 'جلسه‌ی والدین صنف‌های دوازدهم (نمونه)',
            'body' => "جلسه‌ی والدین شاگردان صنف دوازدهم روز پنجشنبه ساعت ۹ صبح در تالار مکتب برگزار می‌شود.\nحضور پدر یا سرپرست هر شاگرد ضروری است.",
            'audience' => AnnouncementAudience::All, 'status' => AnnouncementStatus::Published, 'is_pinned' => true,
            'published_at' => now(), 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        Announcement::create([
            'title' => 'تسلیم نمرات امتحان سالانه (نمونه)',
            'body' => 'معلمان محترم، لطفاً نمرات امتحان سالانه را تا آخر هفته در سیستم ثبت کنید تا نتایج نهایی شود.',
            'audience' => AnnouncementAudience::Teachers, 'status' => AnnouncementStatus::Published,
            'published_at' => now(), 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
    }

    private function letters(User $user): void
    {
        if (Letter::exists()) {
            return;
        }
        $letters = app(LetterService::class);
        $issued = $letters->createOutgoing([
            'type' => 'general', 'letter_date' => now()->toDateString(), 'recipient' => 'ریاست معارف ولایت', 'organization' => 'ریاست معارف',
            'subject' => 'گزارش شمار شاگردان سال جاری (نمونه)',
            'body' => "به مقام محترم ریاست معارف ولایت!\n\nبا عرض سلام، شمار شاگردان این مکتب در سال تحصیلی جاری به اطلاع رسانیده می‌شود.\nجدول مفصل ضمیمه است.\n\nبا احترام",
        ], $user);
        $letters->issue($issued, $user);

        $letters->createOutgoing([
            'type' => 'general', 'letter_date' => now()->toDateString(), 'recipient' => 'مدیریت لیسه‌ی همسایه',
            'subject' => 'دعوت به مسابقه‌ی علمی (نمونه‌ی پیش‌نویس)',
            'body' => "به مدیریت محترم لیسه!\n\nاین یک پیش‌نویس نمونه است: هنوز شماره ندارد و روی چاپ آن «پیش‌نویس» نوشته می‌شود. برای رسمی کردن، «صدور و شماره گرفتن» را بزنید.",
        ], $user);
    }
}
