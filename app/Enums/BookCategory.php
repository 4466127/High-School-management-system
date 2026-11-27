<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** دسته‌ی کتاب در کتابخانه (مرحله‌ی ۱۷)؛ نزدیک به دسته‌های اصلی صنف‌بندی کتابخانه‌ها، به زبان مکاتب. */
enum BookCategory: string
{
    use HasLabel;

    case Textbook = 'textbook';
    case Reference = 'reference';
    case Religion = 'religion';
    case Literature = 'literature';
    case Language = 'language';
    case Science = 'science';
    case Math = 'math';
    case Technology = 'technology';
    case Social = 'social';
    case History = 'history';
    case Arts = 'arts';
    case Children = 'children';
    case Teacher = 'teacher';
    case General = 'general';
}
