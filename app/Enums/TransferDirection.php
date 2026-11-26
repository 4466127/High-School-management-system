<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** انتقال میان مکاتب (جدا از ارتقای صنف در همین مکتب): out = به مکتب دیگر، in = از مکتب دیگر */
enum TransferDirection: string
{
    use HasLabel;

    case Out = 'out';
    case In = 'in';

    public function badgeClass(): string
    {
        return $this === self::Out ? 'text-bg-secondary' : 'text-bg-info';
    }
}
