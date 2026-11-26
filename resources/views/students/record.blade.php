{{-- سوابق تحصیلی یک دانش‌آموز؛ با $transfer = سند انتقال. شکل ساده و قابل تغییر، نه فورم رسمی وزارت. --}}
@extends('layouts.print', ['title' => __($transfer ? 'transfers.certificate_title' : 'transfers.record_title').' — '.$student->full_name, 'noHeader' => true])

@php
    $fa = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $mark = fn ($v) => $v !== null ? \App\Support\Digits::toPersian(\App\Services\MarkService::format($v)) : '—';
    $signatures = array_values(array_filter([(string) \App\Models\Setting::get('letter_signature_1'), (string) \App\Models\Setting::get('letter_signature_2')]));
@endphp

@section('content')
    <div class="alert alert-warning small noprint">{{ __('transfers.template_note') }}</div>

    @include('layouts.partials.letterhead', ['withAddress' => true])

    <h1 class="report-card-title">{{ __($transfer ? 'transfers.certificate_title' : 'transfers.record_title') }}</h1>

    <table class="report-card-info">
        <tr>
            <th>{{ __('students.full_name') }}</th><td>{{ $student->full_name }}</td>
            <th>{{ __('students.father_name') }}</th><td>{{ $student->father_name }}</td>
        </tr>
        <tr>
            <th>{{ __('students.admission_no') }}</th><td>{{ $fa($student->admission_no) }}</td>
            <th>{{ __('students.birth_date') }}</th><td>{{ $student->birth_date ? \App\Support\JalaliDate::format($student->birth_date, 'long') : '—' }}</td>
        </tr>
        <tr>
            <th>{{ __('students.grandfather_name') }}</th><td>{{ $student->grandfather_name ?: '—' }}</td>
            <th>{{ __('students.admission_date') }}</th><td>{{ $student->admission_date ? \App\Support\JalaliDate::format($student->admission_date, 'long') : '—' }}</td>
        </tr>
        @if ($transfer)
            <tr>
                <th>{{ __('transfers.other_school_out') }}</th><td>{{ collect([$transfer->other_school, $transfer->other_province])->filter()->join('، ') }}</td>
                <th>{{ __('transfers.transfer_date') }}</th><td>{{ \App\Support\JalaliDate::format($transfer->transfer_date, 'long') }}</td>
            </tr>
            <tr>
                <th>{{ __('transfers.reason') }}</th><td>{{ $transfer->reason }}</td>
                <th>{{ __('transfers.clearance') }}</th><td>{{ (float) $transfer->outstanding_balance > 0 ? __('transfers.outstanding', ['amount' => \App\Support\Money::format($transfer->outstanding_balance)]) : __('transfers.clearance_ok') }}</td>
            </tr>
        @endif
        @if ($student->certificate_no)
            <tr>
                <th>{{ __('archive.certificate_no') }}</th><td>{{ $fa($student->certificate_no) }}</td>
                <th>{{ __('archive.certificate_date') }}</th><td>{{ $student->certificate_date ? \App\Support\JalaliDate::format($student->certificate_date, 'long') : '—' }}</td>
            </tr>
        @endif
    </table>

    @foreach ($incoming as $in)
        <p class="small mb-1">{{ __('transfers.other_school_in') }}: {{ $in->other_school }} — @jdate($in->transfer_date)@if ($in->last_grade) — {{ __('transfers.last_grade') }}: {{ $in->last_grade }}@endif</p>
    @endforeach

    <table class="table table-bordered table-sm align-middle mt-2">
        <thead>
        <tr>
            <th>{{ __('transfers.year') }}</th>
            <th>{{ __('transfers.grade') }}</th>
            <th>{{ __('students.status') }}</th>
            <th>{{ __('transfers.result') }}</th>
            <th>{{ __('transfers.average') }}</th>
            <th>{{ __('transfers.letter_grade') }}</th>
            <th>{{ __('transfers.attendance') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($history as $h)
            <tr>
                <td>{{ $fa($h['year']) }}</td>
                <td>{{ $h['grade'] }} — {{ $h['section'] }}</td>
                <td class="small">{{ \App\Services\StudentRecordService::statusLabel($h['status']) }}@if ($h['left_on']) (@jdate($h['left_on']))@endif</td>
                <td>{{ $h['finalized'] ? \App\Services\StudentRecordService::resultLabel($h['result']) : __('transfers.not_final') }}</td>
                <td>{{ $mark($h['average']) }}</td>
                <td>{{ $h['letter'] ? __('exams.grades.'.$h['letter']) : '—' }}</td>
                <td class="small">{{ $fa($h['attendance']['present'] + $h['attendance']['late']) }} / {{ $fa($h['attendance']['absent']) }} / {{ $fa($h['attendance']['excused'] + $h['attendance']['sick']) }}</td>
            </tr>
            @if ($h['subjects'] !== [])
                <tr>
                    <td colspan="7" class="small">
                        <span class="text-secondary">{{ __('transfers.subjects') }}:</span>
                        {{ collect($h['subjects'])->map(fn ($s) => $s['name'].' '.$mark($s['final']))->join('، ') }}
                    </td>
                </tr>
            @endif
        @empty
            <tr><td colspan="7" class="text-center text-secondary">{{ __('academics.no_history') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="letter-signs">
        @foreach ($signatures as $i => $title)
            <div class="letter-sign">
                @if ($i === 0 && \App\Models\Setting::get('principal_name'))<div class="fw-bold">{{ \App\Models\Setting::get('principal_name') }}</div>@endif
                <div>{{ $title }}</div>
                <div class="letter-sign-line">{{ __('letters.signature') }}</div>
            </div>
        @endforeach
        @if (\App\Models\Setting::enabled('letter_show_stamp'))<div class="letter-stamp">{{ __('letters.stamp') }}</div>@endif
    </div>
@endsection
