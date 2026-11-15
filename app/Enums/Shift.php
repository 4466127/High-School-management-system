<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Shift: string
{
    use HasLabel;

    case Morning = 'morning';
    case Afternoon = 'afternoon';
}
