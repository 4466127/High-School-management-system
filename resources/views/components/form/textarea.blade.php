@props(['name', 'label' => null, 'value' => null, 'required' => false, 'rows' => 3, 'help' => null, 'useOld' => true])

@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $current = $useOld ? old($dotName, $value) : $value;
    $error = $useOld ? $errors->first($dotName) : null;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              {{ $attributes->except(['class', 'id'])->class(['form-control', 'is-invalid' => (bool) $error]) }}>{{ $current }}</textarea>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @if ($error)
        <div class="invalid-feedback">{{ $error }}</div>
    @endif
</div>
