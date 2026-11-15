<?php

namespace Tests\Unit;

use App\Support\Digits;
use PHPUnit\Framework\TestCase;

class DigitsTest extends TestCase
{
    public function test_converts_persian_and_arabic_digits_to_latin(): void
    {
        $this->assertSame('1405/08/24', Digits::toLatin('۱۴۰۵/۰۸/۲۴'));
        $this->assertSame('0123456789', Digits::toLatin('٠١٢٣٤٥٦٧٨٩'));
        $this->assertSame('12.5', Digits::toLatin('۱۲٫۵'));
        $this->assertSame('احمد 7', Digits::toLatin('احمد ۷'));
    }

    public function test_converts_latin_digits_to_persian(): void
    {
        $this->assertSame('۱۴۰۵/۰۸/۲۴', Digits::toPersian('1405/08/24'));
        $this->assertSame('۴۰', Digits::toPersian(40));
        $this->assertSame('', Digits::toPersian(null));
    }
}
