<?php

namespace App\Services;

use App\Enums\CourseChannel;
use App\Enums\CourseEntryType;

/**
 * جمع‌های دفتر مالی کورس (بدون پایگاه داده؛ تست Unit دارد). همه به پول (سنت، int).
 *
 * مجموع کمک‌ها            = کمک‌های صندوق + کمک‌های مستقیم به معلم
 * فیس اشتراک              = فیس شاگردان (همیشه به صندوق)
 * مانده‌ی صندوق کورس       = کمک‌های صندوق + فیس − معاش از صندوق − مصارف
 * دریافتی هر معلم          = معاش از صندوق + کمک‌های مستقیم به او
 * سطرهای باطل حساب نمی‌شوند (پیش از فرستادن به این کلاس جدا شوند).
 */
final class CourseLedgerCalculator
{
    /**
     * @param  iterable<array{type: CourseEntryType, channel: CourseChannel, staff_id: ?int, cents: int}>  $entries
     * @return array{donations_cashbox: int, donations_direct: int, donations_total: int, fees: int, salaries: int, expenses: int, balance: int, teachers: array<int, array{salary: int, direct: int, total: int}>}
     */
    public static function summarize(iterable $entries): array
    {
        $sum = ['donations_cashbox' => 0, 'donations_direct' => 0, 'fees' => 0, 'salaries' => 0, 'expenses' => 0];
        $teachers = [];

        foreach ($entries as $e) {
            $cents = $e['cents'];
            $key = match (true) {
                $e['type'] === CourseEntryType::Donation && $e['channel'] === CourseChannel::DirectToTeacher => 'donations_direct',
                $e['type'] === CourseEntryType::Donation => 'donations_cashbox',
                $e['type'] === CourseEntryType::Fee => 'fees',
                $e['type'] === CourseEntryType::TeacherSalary => 'salaries',
                default => 'expenses',
            };
            $sum[$key] += $cents;

            if (in_array($key, ['donations_direct', 'salaries'], true) && $e['staff_id'] !== null) {
                $teachers[$e['staff_id']] ??= ['salary' => 0, 'direct' => 0, 'total' => 0];
                $teachers[$e['staff_id']][$key === 'salaries' ? 'salary' : 'direct'] += $cents;
            }
        }

        foreach ($teachers as $id => $t) {
            $teachers[$id]['total'] = $t['salary'] + $t['direct'];
        }

        return [
            'donations_cashbox' => $sum['donations_cashbox'],
            'donations_direct' => $sum['donations_direct'],
            'donations_total' => $sum['donations_cashbox'] + $sum['donations_direct'],
            'fees' => $sum['fees'],
            'salaries' => $sum['salaries'],
            'expenses' => $sum['expenses'],
            'balance' => $sum['donations_cashbox'] + $sum['fees'] - $sum['salaries'] - $sum['expenses'],
            'teachers' => $teachers,
        ];
    }

    /** اثر یک سطر بر مانده‌ی صندوق (کمک مستقیم = صفر) */
    public static function cashEffect(CourseEntryType $type, CourseChannel $channel, int $cents): int
    {
        return match (true) {
            $type === CourseEntryType::Donation && $channel === CourseChannel::Cashbox => $cents,
            $type === CourseEntryType::Donation => 0,
            $type === CourseEntryType::Fee => $cents,
            default => -$cents,
        };
    }
}
