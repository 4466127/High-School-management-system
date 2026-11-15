<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * سطح تحصیل از متن آزاد به فهرست ثابت (App\Enums\EducationLevel).
 * مقدارهای قبلی که شناخته می‌شوند تبدیل می‌شوند؛ بقیه در یادداشت کارمند نگه داشته و خانه خالی می‌شود
 * (هیچ معلوماتی از بین نمی‌رود).
 */
return new class extends Migration
{
    private const MAP = [
        'grade_12' => ['دوازده پاس', 'دوازده‌پاس', 'دوازدهم', '12 پاس', '۱۲ پاس', 'بکلوریا', 'فارغ صنف دوازدهم'],
        'grade_14' => ['چهارده پاس', 'چهارده‌پاس', '14 پاس', '۱۴ پاس', 'دارالمعلمین'],
        'bachelor' => ['لیسانس', 'کارشناسی', 'لسانس', 'bachelor'],
        'master' => ['ماستری', 'ماستر', 'کارشناسی ارشد', 'فوق لیسانس', 'master'],
        'doctorate' => ['دکترا', 'دوکتورا', 'داکتری', 'phd'],
    ];

    public function up(): void
    {
        $known = array_keys(self::MAP);

        foreach (DB::table('staff')->whereNotNull('education')->get(['id', 'education', 'notes']) as $row) {
            if (in_array($row->education, $known, true)) {
                continue;
            }

            $value = trim($row->education);
            $level = null;
            foreach (self::MAP as $key => $aliases) {
                if (in_array(mb_strtolower($value), array_map('mb_strtolower', $aliases), true)) {
                    $level = $key;
                    break;
                }
            }

            $update = ['education' => $level];
            if ($level === null && $value !== '') {
                $update['notes'] = trim(($row->notes ? $row->notes."\n" : '').'تحصیلات (قبلی): '.$value);
            }
            DB::table('staff')->where('id', $row->id)->update($update);
        }
    }

    public function down(): void
    {
        // تبدیل برگشت‌پذیر نیست؛ کلیدها (bachelor …) معتبر می‌مانند.
    }
};
