<?php

namespace App\Rules;

use App\Services\FinanceService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** مبلغ افغانی: «۱٬۲۵۰»، «1250.50»؛ حداکثر دو رقم اعشار. new ValidAmount(allowZero: true) برای تخفیف. */
class ValidAmount implements ValidationRule
{
    public function __construct(private readonly bool $allowZero = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cents = is_scalar($value) ? FinanceService::parseAmount((string) $value) : null;

        if ($cents === null || $cents < 0 || (! $this->allowZero && $cents === 0)) {
            $fail('finance.amount_invalid')->translate();
        }
    }
}
