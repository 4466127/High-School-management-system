{{-- خانه‌های نوع فیس. $type (یا null)، $prefix، $useOld --}}
@php
    $period = $useOld ? old('period', $type?->period?->value ?? 'monthly') : ($type?->period?->value ?? 'monthly');
    $active = $useOld ? (bool) old('is_active', $type?->is_active ?? true) : (bool) ($type?->is_active ?? true);
@endphp
<x-form.input name="name" :label="__('finance.type_name')" :value="$type?->name" required maxlength="100" :placeholder="__('finance.type_name_ph')" :use-old="$useOld" id="{{ $prefix }}name" />
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="{{ $prefix }}period" class="form-label">{{ __('finance.period') }} <span class="text-danger">*</span></label>
        <select name="period" id="{{ $prefix }}period" class="form-select">
            @foreach (\App\Enums\FeePeriod::options() as $value => $label)
                <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <x-form.input class="col-md-6 mb-3" name="sort_order" type="number" min="1" max="99" :label="__('finance.sort_order')" :value="$type?->sort_order ?? 1" required :use-old="$useOld" id="{{ $prefix }}sort" />
</div>
<div class="form-check">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $prefix }}active" @checked($active)>
    <label class="form-check-label" for="{{ $prefix }}active">{{ __('finance.active') }}</label>
</div>
