<?php

namespace App\Support;

use App\Models\Setting;

/**
 * تنظیمات قالب کارنامه و ترانسکریپت (مرحله‌ی ۱۴). مقدار پیش‌فرض در config/school.php ← defaults.
 * چاپ‌ها فقط از همین کلاس می‌خوانند تا عنوان پیش‌فرض و «روشن/خاموش» در یک جا باشد.
 */
final class ExamTemplate
{
    public const REPORT_CARD_KEYS = [
        'reportcard_title', 'reportcard_subtitle', 'reportcard_show_exams', 'reportcard_show_attendance', 'reportcard_show_grade',
        'reportcard_show_rules', 'reportcard_show_remarks', 'reportcard_signature_1', 'reportcard_signature_2', 'reportcard_signature_3',
        'reportcard_show_stamp', 'reportcard_footer',
    ];

    public const TRANSCRIPT_KEYS = [
        'transcript_title', 'transcript_grade_ids', 'transcript_signature_1', 'transcript_signature_2', 'transcript_show_stamp', 'transcript_footer',
    ];

    public const SWITCHES = [
        'reportcard_show_exams', 'reportcard_show_attendance', 'reportcard_show_grade', 'reportcard_show_rules', 'reportcard_show_remarks',
        'reportcard_show_stamp', 'transcript_show_stamp',
    ];

    /** @return array{title: string, subtitle: string, exams: bool, attendance: bool, grade: bool, rules: bool, remarks: bool, signatures: list<string>, stamp: bool, footer: string} */
    public static function reportCard(): array
    {
        return [
            'title' => self::text('reportcard_title') ?: __('exams.reportcard'),
            'subtitle' => self::text('reportcard_subtitle'),
            'exams' => Setting::enabled('reportcard_show_exams'),
            'attendance' => Setting::enabled('reportcard_show_attendance'),
            'grade' => Setting::enabled('reportcard_show_grade'),
            'rules' => Setting::enabled('reportcard_show_rules'),
            'remarks' => Setting::enabled('reportcard_show_remarks'),
            'signatures' => self::signatures(['reportcard_signature_1', 'reportcard_signature_2', 'reportcard_signature_3']),
            'stamp' => Setting::enabled('reportcard_show_stamp'),
            'footer' => self::text('reportcard_footer'),
        ];
    }

    /** @return array{title: string, signatures: list<string>, stamp: bool, footer: string} */
    public static function transcript(): array
    {
        return [
            'title' => self::text('transcript_title') ?: __('transcripts.default_title'),
            'signatures' => self::signatures(['transcript_signature_1', 'transcript_signature_2']),
            'stamp' => Setting::enabled('transcript_show_stamp'),
            'footer' => self::text('transcript_footer'),
        ];
    }

    private static function text(string $key): string
    {
        return trim((string) Setting::get($key, ''));
    }

    /** @return list<string> */
    private static function signatures(array $keys): array
    {
        return array_values(array_filter(array_map(fn ($k) => self::text($k), $keys), fn ($v) => $v !== ''));
    }
}
