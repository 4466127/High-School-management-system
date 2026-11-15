{{-- <x-form.select name="gender" :label="..." :options="['male' => 'پسر']" :value="$model->gender?->value" placeholder="— انتخاب —" /> --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null])

@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($dotName, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @required($required)
            {{ $attributes->except(['class', 'id'])->class(['form-select', 'is-invalid' => $errors->has($dotName)]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @error($dotName)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
