{{-- عکس کوچک یا نشان جای خالی. <x-avatar :src="$student->photo_path ? route('students.photo', $student) : null" size="48" /> --}}
@props(['src' => null, 'size' => 48, 'alt' => ''])

@if ($src)
    <img src="{{ $src }}" alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}" loading="lazy"
         {{ $attributes->merge(['class' => 'avatar rounded-circle object-fit-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'avatar avatar-empty rounded-circle']) }} style="width: {{ $size }}px; height: {{ $size }}px;" aria-hidden="true">
        <i class="fa-solid fa-user"></i>
    </span>
@endif
