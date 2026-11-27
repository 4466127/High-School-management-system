<?php

namespace App\Models;

use App\Enums\ChemicalHazard;
use App\Enums\CopySource;
use App\Enums\LabItemKind;
use App\Enums\LabUnit;
use App\Services\AttendanceService;
use App\Support\PersianText;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک قلم موجودی لابراتوار (مرحله‌ی ۱۸). مقدار فقط از راه LabService و همیشه با رویداد (lab_item_events) تغییر می‌کند.
 * هشدارها ذخیره نمی‌شوند: کمبود (مقدار کمتر یا برابر حد)، تاریخ‌گذشته، نزدیک ختم (۶۰ روز)، خراب.
 */
#[Fillable(LabItem::FIELDS)]
class LabItem extends Model
{
    public const FIELDS = ['lab_id', 'name', 'kind', 'code', 'unit', 'min_quantity', 'location', 'hazard', 'expires_on', 'unit_price', 'source', 'specification', 'notes', 'is_active'];

    /** «نزدیک ختم» = تا این شمار روز */
    public const EXPIRY_WARNING_DAYS = 60;

    protected function casts(): array
    {
        return [
            'kind' => LabItemKind::class, 'unit' => LabUnit::class, 'hazard' => ChemicalHazard::class, 'source' => CopySource::class,
            'quantity' => 'decimal:2', 'damaged_quantity' => 'decimal:2', 'min_quantity' => 'decimal:2', 'unit_price' => 'decimal:2',
            'expires_on' => 'date', 'is_active' => 'boolean',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LabItemEvent::class)->latest('event_date')->latest('id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return PersianText::whereMatches($query, ['name', 'code', 'specification', 'location'], $term);
    }

    /** فیلتر هشدار: low | expired | expiring | damaged */
    public function scopeAlert(Builder $query, string $alert): Builder
    {
        $today = AttendanceService::today();

        return match ($alert) {
            'low' => $query->whereNotNull('min_quantity')->whereColumn('quantity', '<=', 'min_quantity'),
            'expired' => $query->whereNotNull('expires_on')->where('expires_on', '<', $today->toDateString()),
            'expiring' => $query->whereBetween('expires_on', [$today->toDateString(), $today->addDays(self::EXPIRY_WARNING_DAYS)->toDateString()]),
            'damaged' => $query->where('damaged_quantity', '>', 0),
            default => $query,
        };
    }

    public function isLow(): bool
    {
        return $this->min_quantity !== null && Quantity::toHundredths($this->quantity) <= Quantity::toHundredths($this->min_quantity);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(AttendanceService::today()->startOfDay());
    }

    public function isExpiring(): bool
    {
        return $this->expires_on !== null && ! $this->isExpired() && $this->expires_on->lte(AttendanceService::today()->addDays(self::EXPIRY_WARNING_DAYS));
    }

    public function hasHazard(): bool
    {
        return $this->hazard !== null && $this->hazard !== ChemicalHazard::None;
    }
}
