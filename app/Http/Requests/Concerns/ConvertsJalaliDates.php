<?php

namespace App\Http\Requests\Concerns;

use App\Support\JalaliDate;

/**
 * برای Form Requestهایی که خانه‌ی تاریخ شمسی دارند (<x-jalali-date>).
 * اعتبارسنجی روی متن شمسی (قاعده‌ی ValidJalaliDate) انجام می‌شود تا در صورت خطا همان متن به کاربر برگردد؛
 * validated() تاریخ‌ها را به میلادی Y-m-d تبدیل می‌کند تا مستقیم در پایگاه داده ذخیره شود.
 *
 * کلاس استفاده‌کننده باید متد jalaliDateFields() را تعریف کند. از validated() استفاده کنید، نه safe().
 */
trait ConvertsJalaliDates
{
    /** @return list<string> */
    abstract protected function jalaliDateFields(): array;

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        foreach ($this->jalaliDateFields() as $field) {
            $value = data_get($data, $field);
            if (is_string($value) && $value !== '') {
                data_set($data, $field, JalaliDate::parse($value)?->toDateString());
            }
        }

        return data_get($data, $key, $default);
    }
}
