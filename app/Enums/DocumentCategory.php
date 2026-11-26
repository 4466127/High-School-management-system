<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** دسته‌ی سند دانش‌آموز (مرحله‌ی ۱۳). دیدن همه‌ی اسناد فقط با students.manage (StudentPolicy::viewSensitive). */
enum DocumentCategory: string
{
    use HasLabel;

    case Registration = 'registration';
    case Identity = 'identity';
    case Photo = 'photo';
    case Certificate = 'certificate';
    case ReportCard = 'report_card';
    case Transfer = 'transfer';
    case Medical = 'medical';
    case Other = 'other';

    public function icon(): string
    {
        return match ($this) {
            self::Registration => 'fa-file-signature',
            self::Identity => 'fa-id-card',
            self::Photo => 'fa-image',
            self::Certificate => 'fa-award',
            self::ReportCard => 'fa-file-lines',
            self::Transfer => 'fa-right-left',
            self::Medical => 'fa-notes-medical',
            self::Other => 'fa-file',
        };
    }
}
