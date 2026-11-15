<?php

namespace Tests\Unit;

use App\Support\PersianText;
use PHPUnit\Framework\TestCase;

class PersianTextTest extends TestCase
{
    public function test_arabic_letters_become_persian(): void
    {
        $this->assertSame('علی کریمی', PersianText::normalize('علي كريمي'));
        $this->assertSame('موسی', PersianText::normalize('موسى'));
        $this->assertSame('محمد', PersianText::normalize('محـــمد'));
    }

    public function test_search_terms_are_split_and_normalized(): void
    {
        $this->assertSame(['علی', '1405'], PersianText::searchTerms('  علي   ۱۴۰۵ '));
        $this->assertSame([], PersianText::searchTerms('   '));
        $this->assertSame([], PersianText::searchTerms(null));
    }

    public function test_like_escapes_wildcards(): void
    {
        $this->assertSame('%50\%%', PersianText::like('50%'));
        $this->assertSame('%a\_b%', PersianText::like('a_b'));
    }
}
