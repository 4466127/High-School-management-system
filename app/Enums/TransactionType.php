<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TransactionType: string
{
    use HasLabel;

    case Income = 'income';
    case Expense = 'expense';

    public function badgeClass(): string
    {
        return $this === self::Income ? 'text-bg-success' : 'text-bg-danger';
    }
}
