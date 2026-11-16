<?php

namespace Tests\Unit;

use App\Services\FinanceService;
use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_cents_conversion_avoids_float_errors(): void
    {
        $this->assertSame(125050, Money::toCents('1,250.50'));
        $this->assertSame(125050, Money::toCents('۱٬۲۵۰٫۵۰'));
        $this->assertSame(30, Money::toCents(0.1 + 0.2));
        $this->assertSame('1250.50', Money::fromCents(125050));
        $this->assertSame('1250.5', Money::plain('1250.50'));
        $this->assertSame('1500', Money::plain('1500.00'));
    }

    public function test_display_format(): void
    {
        $this->assertSame('۱٬۲۵۰ افغانی', Money::format('1250.00'));
        $this->assertSame('۱٬۲۵۰٫۵۰ افغانی', Money::format('1250.5'));
        $this->assertSame('−۲۰۰', Money::format(-200, false));
    }

    public function test_user_amount_parsing(): void
    {
        $this->assertSame(150000, FinanceService::parseAmount('۱۵۰۰'));
        $this->assertSame(150050, FinanceService::parseAmount('1,500.5'));
        $this->assertNull(FinanceService::parseAmount('12.345'));
        $this->assertNull(FinanceService::parseAmount('abc'));
        $this->assertNull(FinanceService::parseAmount(''));
    }
}
