{{-- کارت هویت دانش‌آموز (۸۵٫۶ × ۵۴ میلی‌متر). معلومات و نام از تنظیمات مکتب؛ هیچ متن رسمی ثابت نیست. --}}
@extends('layouts.print', ['title' => __('cards.title'), 'noHeader' => true])

@php
    $school = (string) \App\Models\Setting::get('school_name');
    $headerLine = (string) \App\Models\Setting::get('header_line');
    $hasLogo = (string) \App\Models\Setting::get('logo_path') !== '';
    $validUntil = $year ? \App\Support\JalaliDate::format($year->end_date, 'long') : '';
@endphp

@section('content')
    <div class="id-cards">
        @forelse ($enrollments as $e)
            @php($s = $e->student)
            <div class="id-card">
                <div class="id-card-head">
                    @if ($hasLogo)<img src="{{ route('school.logo') }}" alt="" class="id-card-logo">@endif
                    <div class="id-card-school">
                        @if ($headerLine !== '')<div class="id-card-line">{{ $headerLine }}</div>@endif
                        <div class="fw-bold">{{ $school }}</div>
                        <div class="id-card-line">{{ __('cards.card_title') }}</div>
                    </div>
                </div>
                <div class="id-card-body">
                    <div class="id-card-photo">
                        @if ($s->photo_path)<img src="{{ route('students.photo', $s) }}" alt="">@else<span>{{ __('cards.no_photo') }}</span>@endif
                    </div>
                    <dl class="id-card-info">
                        <dt>{{ __('cards.name') }}</dt><dd class="fw-bold">{{ $s->full_name }}</dd>
                        <dt>{{ __('cards.father') }}</dt><dd>{{ $s->father_name }}</dd>
                        <dt>{{ __('cards.admission_no') }}</dt><dd>@fa($s->admission_no)</dd>
                        <dt>{{ __('cards.class') }}</dt><dd>{{ $e->section->gradeLevel->name }} — {{ $e->section->name }}</dd>
                        <dt>{{ __('cards.year') }}</dt><dd>@fa($year->name)</dd>
                    </dl>
                </div>
                <div class="id-card-foot">
                    <span>{{ __('cards.valid_until') }}: {{ $validUntil }}</span>
                    <span class="id-card-sign">{{ __('cards.signature') }}</span>
                </div>
            </div>
        @empty
            <p class="text-secondary">{{ __('cards.none') }}</p>
        @endforelse
    </div>
@endsection
