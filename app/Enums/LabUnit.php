<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** واحد مقدار (مرحله‌ی ۱۸). */
enum LabUnit: string
{
    use HasLabel;

    case Piece = 'piece';
    case Set = 'set';
    case Box = 'box';
    case Gram = 'gram';
    case Kilogram = 'kilogram';
    case Milliliter = 'ml';
    case Liter = 'liter';
    case Meter = 'meter';
}
