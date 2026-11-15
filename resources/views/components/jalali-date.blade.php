{{--
    خانه‌ی تاریخ شمسی با انتخاب‌گر (public/assets/js/app.js، input[data-jdate]).
    <x-jalali-date name="birth_date" :label="__('...')" :value="$student->birth_date" required />
    مقدار به شکل ۱۴۰۵/۰۸/۲۴ فرستاده می‌شود؛ Form Request با ConvertsJalaliDates آن را میلادی می‌کند.
    inputmode=numeric عمداً گذاشته نشده: کیبورد عددی موبایل «/» ندارد.
--}}
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'placeholder' => null, 'useOld' => true])

@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $bag = ($errors ?? null) instanceof \Illuminate\Support\ViewErrorBag ? $errors : new \Illuminate\Support\ViewErrorBag;
    $errorBag = $useOld ? $bag : new \Illuminate\Support\ViewErrorBag;
    $formatted = $value instanceof \DateTimeInterface || (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value))
        ? \App\Support\JalaliDate::format($value)
        : $value;
    $current = $useOld ? old($dotName, $formatted) : $formatted;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $current }}"
           data-jdate autocomplete="off" dir="ltr"
           placeholder="{{ $placeholder ?? __('app.date_placeholder') }}"
           @required($required)
           @class(['form-control', 'is-invalid' => $errorBag->has($dotName)])>
    <div class="form-text">{{ $help ?? __('app.date_help') }}</div>
    @if ($errorBag->has($dotName))
        <div class="invalid-feedback">{{ $errorBag->first($dotName) }}</div>
    @endif
</div>
