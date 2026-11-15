{{-- <x-form.input name="first_name" :label="__('...')" :value="$model->first_name" required /> --}}
{{-- useOld=false: مقدار old() و خطا را نشان نده (برای چند فرم هم‌نام در یک صفحه؛ فقط فرمی که فرستاده شده) --}}
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
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
           @if ($current !== null) value="{{ $current }}" @endif
           @if ($ltr) dir="ltr" @endif
           @required($required)
           {{ $attributes->except(['class', 'id'])->class(['form-control', 'is-invalid' => (bool) $error]) }}>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @if ($error)
        <div class="invalid-feedback">{{ $error }}</div>
    @endif
</div>
