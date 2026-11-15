{{--
    <x-form.input name="first_name" :label="__('...')" :value="$model->first_name" :placeholder="..." required />
    type="file" ← دکمه‌ی فارسی «انتخاب فایل» به جای متن انگلیسی مرورگر (school.js نام فایل را نشان می‌دهد).
    useOld=false: مقدار old() و خطا را نشان نده (برای چند فرم هم‌نام در یک صفحه؛ فقط فرمی که فرستاده شده).
--}}
@props(['name', 'label' => null, 'value' => null, 'type' => 'text', 'required' => false, 'help' => null, 'ltr' => false, 'useOld' => true])

@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' || $type === 'file' ? null : ($useOld ? old($dotName, $value) : $value);
    $error = $useOld ? $errors->first($dotName) : null;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif

    @if ($type === 'file')
        <label @class(['file-pick', 'is-invalid' => (bool) $error])>
            <input type="file" id="{{ $id }}" name="{{ $name }}" @required($required)
                   {{ $attributes->except(['class', 'id'])->class(['file-pick-input']) }}>
            <span class="file-pick-btn"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> {{ __('app.choose_file') }}</span>
            <span class="file-pick-name" data-empty="{{ __('app.no_file') }}">{{ __('app.no_file') }}</span>
        </label>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
               @if ($current !== null) value="{{ $current }}" @endif
               @if ($ltr) dir="ltr" @endif
               @required($required)
               {{ $attributes->except(['class', 'id'])->class(['form-control', 'is-invalid' => (bool) $error]) }}>
    @endif

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @if ($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @endif
</div>
