<?php

namespace App\Http\Requests\Labs;

use App\Enums\ChemicalHazard;
use App\Enums\CopySource;
use App\Enums\LabItemKind;
use App\Enums\LabUnit;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Models\LabItem;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use App\Services\FinanceService;
use App\Support\Digits;
use App\Support\Money;
use App\Support\Quantity;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** ثبت و ویرایش قلم موجودی لابراتوار (مرحله‌ی ۱۸؛ مسیر با can:labs.manage). مقدار اول فقط هنگام ثبت. */
class LabItemRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public function authorize(): bool
    {
        return true;
    }

    protected function jalaliDateFields(): array
    {
        return ['expires_on'];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => trim(Digits::toLatin((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $qty = fn (string $attr, mixed $value, Closure $fail) => Quantity::parse((string) $value) === null ? $fail(__('labs.quantity_invalid')) : null;
        $item = $this->route('item');

        return array_merge([
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(LabItemKind::class)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('lab_items', 'code')->ignore($item?->id)],
            'unit' => ['required', Rule::enum(LabUnit::class)],
            'min_quantity' => ['nullable', $qty],
            'location' => ['nullable', 'string', 'max:50'],
            'hazard' => ['nullable', Rule::enum(ChemicalHazard::class)],
            'expires_on' => ['nullable', new ValidJalaliDate],
            'unit_price' => ['nullable', new ValidAmount(allowZero: true)],
            'source' => ['required', Rule::enum(CopySource::class)],
            'specification' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->isMethod('POST') ? ['quantity' => ['nullable', $qty]] : []);
    }

    public function attributes(): array
    {
        return __('labs.attributes');
    }

    /** @return array<string, mixed> */
    public function itemData(): array
    {
        $v = $this->validated();
        $data = collect($v)->only(LabItem::FIELDS)->all();
        $data['min_quantity'] = ($v['min_quantity'] ?? '') !== '' && $v['min_quantity'] !== null ? Quantity::fromHundredths(Quantity::parse((string) $v['min_quantity'])) : null;
        $cents = FinanceService::parseAmount($v['unit_price'] ?? null);
        $data['unit_price'] = $cents !== null ? Money::fromCents($cents) : null;
        $data['code'] = ($v['code'] ?? '') !== '' ? $v['code'] : null;
        $data['hazard'] = ($v['hazard'] ?? '') !== '' ? $v['hazard'] : null;
        $data['is_active'] = $this->isMethod('POST') || $this->boolean('is_active');

        return $data;
    }

    /** مقدار اول (صدم) */
    public function initialQuantity(): int
    {
        return Quantity::parse((string) ($this->validated()['quantity'] ?? '')) ?? 0;
    }
}
