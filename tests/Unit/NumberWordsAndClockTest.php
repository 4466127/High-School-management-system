<?php

namespace Tests\Unit;

use App\Support\Clock;
use App\Support\NumberWords;
use Carbon\Carbon;
use Tests\TestCase;

/** 2.1.0: نمره به حروف دری (فورم وزارت معارف) و وقت ۱۲ ساعته */
class NumberWordsAndClockTest extends TestCase
{
    public function test_numbers_in_dari_words(): void
    {
        $this->assertSame('صفر', NumberWords::fa(0));
        $this->assertSame('نه', NumberWords::fa(9));
        $this->assertSame('هژده', NumberWords::fa(18));
        $this->assertSame('هشتاد و هفت', NumberWords::fa(87));
        $this->assertSame('یکصد', NumberWords::fa(100));
        $this->assertSame('هشتاد و هفت و نیم', NumberWords::fa(87.5));
        $this->assertSame('هفتصد و هشتاد و دو', NumberWords::fa('782'));
        $this->assertSame('یک هزار و دوصد و چهل و پنج', NumberWords::fa(1245));
        $this->assertSame('نود و یک ممیز بیست و پنج', NumberWords::fa(91.25));
        $this->assertSame('شصت ممیز دو', NumberWords::fa(60.2));
        $this->assertSame('هشتاد', NumberWords::fa('۸۰'));
        $this->assertSame('', NumberWords::fa(null));
    }

    public function test_twelve_hour_clock(): void
    {
        $this->assertSame('۱:۰۰ – ۱:۴۰', Clock::range('13:00:00', '13:40'));
        $this->assertSame('۸:۰۰ – ۸:۴۰', Clock::range('08:00', '08:40'));
        $this->assertSame('۱۲:۰۵ ب.ظ', Clock::withPeriod('12:05'));
        $this->assertSame('۱۲:۳۰ ق.ظ', Clock::withPeriod('00:30'));
        $this->assertSame('۴:۱۵ ب.ظ', Clock::withPeriod(Carbon::parse('2026-11-27 16:15')));
        $this->assertSame('', Clock::short(null));
    }
}
