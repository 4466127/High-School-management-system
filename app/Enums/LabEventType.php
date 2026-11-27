<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع رویداد دفتر موجودی لابراتوار (مرحله‌ی ۱۸). علامت تغییر را LabService تعیین می‌کند. */
enum LabEventType: string
{
    use HasLabel;

    case Received = 'received';
    case Used = 'used';
    case Damaged = 'damaged';
    case Repaired = 'repaired';
    case WrittenOff = 'written_off';
    case Lost = 'lost';
    case Disposed = 'disposed';
    case Adjusted = 'adjusted';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Received => 'text-bg-success',
            self::Used => 'text-bg-primary',
            self::Damaged => 'text-bg-warning',
            self::Repaired => 'text-bg-success',
            self::WrittenOff => 'text-bg-secondary',
            self::Lost => 'text-bg-danger',
            self::Disposed => 'text-bg-secondary',
            self::Adjusted => 'text-bg-light',
        };
    }
}
