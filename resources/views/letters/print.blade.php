{{-- چاپ مکتوب صادره با سربرگ مکتب، شماره و تاریخ، جای امضاها و مهر (همه از تنظیمات؛ «ذخیره به PDF» مرورگر). --}}
@extends('layouts.print', ['title' => $letter->subject, 'noHeader' => true])

@php
    $signatures = array_values(array_filter([(string) \App\Models\Setting::get('letter_signature_1'), (string) \App\Models\Setting::get('letter_signature_2')]));
    $principal = (string) \App\Models\Setting::get('principal_name');
    $meta = [
        __('letters.letter_no') => $letter->number ? \App\Support\Digits::toPersian($letter->number) : '—',
        __('letters.letter_date') => \App\Support\JalaliDate::format($letter->letter_date, 'long'),
    ];
    if ($letter->attachments->isNotEmpty()) {
        $meta[__('letters.attachments')] = \App\Support\Digits::toPersian($letter->attachments->count());
    }
@endphp

@section('content')
    <div @class(['letter-sheet', 'is-draft' => $letter->isDraft(), 'is-cancelled' => $letter->isCancelled()])>
        @if ($letter->isDraft())<div class="letter-watermark">{{ __('letters.draft_watermark') }}</div>@endif
        @if ($letter->isCancelled())<div class="letter-watermark">{{ __('letters.cancelled_badge') }}</div>@endif

        @include('layouts.partials.letterhead', ['withAddress' => true, 'meta' => $meta])

        @if ($letter->recipient || $letter->organization)
            <p class="mb-1"><span class="fw-bold">{{ __('letters.to_line') }}:</span> {{ collect([$letter->recipient, $letter->organization])->filter()->join('، ') }}</p>
        @endif
        <p class="mb-3"><span class="fw-bold">{{ __('letters.subject_line') }}:</span> {{ $letter->subject }}</p>

        <div class="letter-body letter-print-body">{{ $letter->body }}</div>

        <div class="letter-signs">
            @foreach ($signatures as $i => $title)
                <div class="letter-sign">
                    @if ($i === 0 && $principal !== '')<div class="fw-bold">{{ $principal }}</div>@endif
                    <div>{{ $title }}</div>
                    <div class="letter-sign-line">{{ __('letters.signature') }}</div>
                </div>
            @endforeach
            @if (\App\Models\Setting::enabled('letter_show_stamp'))
                <div class="letter-stamp">{{ __('letters.stamp') }}</div>
            @endif
        </div>
    </div>
@endsection
