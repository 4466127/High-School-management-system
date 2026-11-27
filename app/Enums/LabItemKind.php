<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع قلم موجودی لابراتوار (مرحله‌ی ۱۸). */
enum LabItemKind: string
{
    use HasLabel;

    case Equipment = 'equipment';
    case Glassware = 'glassware';
    case Chemical = 'chemical';
    case Consumable = 'consumable';
    case Specimen = 'specimen';
    case Electronic = 'electronic';
}
