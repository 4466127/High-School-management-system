<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** صادره (مکتوب مکتب) یا وارده (مکتوبی که به مکتب رسیده) */
enum LetterDirection: string
{
    use HasLabel;

    case Outgoing = 'outgoing';
    case Incoming = 'incoming';
}
