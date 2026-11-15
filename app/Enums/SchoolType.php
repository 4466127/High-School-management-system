<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum SchoolType: string
{
    use HasLabel;

    case Government = 'government';
    case Private = 'private';
}
