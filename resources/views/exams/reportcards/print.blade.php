@extends('layouts.print', ['title' => __('exams.reportcard'), 'noHeader' => true])

@php
    $fmt = fn ($n) => $n === null ? '—' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n));
@endphp

@section('content')
    @forelse ($cards as $card)
        <div class="report-card">
            @php($student = $card['enrollment']->student)
            @php($result = $card['result'] ? \App\Enums\FinalResult::from($card['result']) : null)
            @php($hasReexam = ! empty($card['reexam']))
            <header class="print-head">
                <div class="d-flex align-items-center gap-2">
                    @if (\App\Models\Setting::get('logo_path'))<img src="{{ route('school.logo') }}" alt="" width="56" height="56" class="object-fit-contain">@endif
                    <div>
                        <div class="print-school">{{ \App\Models\Setting::get('school_name') }}</div>
                        <div class="small">{{ collect([\App\Models\Setting::get('province'), \App\Models\Setting::get('district')])->filter()->join('، ') }}</div>
                    </div>
                </div>
                <div class="text-start small">{{ __('app.printed_on') }}: @jdate(now())</div>
            </header>
            <h1 class="report-card-title">
                {{ __('exams.reportcard') }} — {{ __('academics.year') }} @fa($section->academicYear->name)
                @if ($card['provisional'])<span class="report-card-provisional">{{ __('exams.provisional') }}</span>@endif
            </h1>

            <table class="report-card-info">
                <tr>
                    <th>{{ __('students.full_name') }}</th><td>{{ $student->full_name }}</td>
                    <th>{{ __('students.father_name') }}</th><td>{{ $student->father_name }}</td>
                </tr>
                <tr>
                    <th>{{ __('academics.grade') }}</th><td>{{ $section->gradeLevel->name }} — {{ $section->name }} ({{ $section->shift->label() }})</td>
                    <th>{{ __('students.admission_no') }} / {{ __('academics.roll_no') }}</th><td>@fa($student->admission_no) / @fa($card['enrollment']->roll_no)</td>
                </tr>
            </table>

            <table class="table table-bordered table-sm align-middle text-center report-card-marks">
                <thead>
                <tr>
                    <th>#</th>
                    <th class="text-start">{{ __('academics.subject') }}</th>
                    @foreach ($card['exams'] as $exam)
                        <th>{{ $exam['name'] }}<div class="small fw-normal">({{ $fmt($exam['max_mark']) }})</div></th>
                    @endforeach
                    <th>{{ __('exams.final_mark') }}<div class="small fw-normal">(۱۰۰)</div></th>
                    @if ($hasReexam)<th>{{ __('exams.reexam_column') }}<div class="small fw-normal">(۱۰۰)</div></th>@endif
                    <th>{{ __('exams.subject_result') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($card['subjects'] as $i => $subject)
                    <tr>
                        <td>@fa($i + 1)</td>
                        <td class="text-start">{{ $subject['name'] }}</td>
                        @foreach ($card['exams'] as $exam)
                            @php($m = $subject['marks'][$exam['id']] ?? null)
                            <td>{{ $m ? ($m['absent'] ? __('exams.absent_short') : $fmt($m['mark'])) : '' }}</td>
                        @endforeach
                        <td class="fw-bold">{{ $subject['final'] !== null ? $fmt($subject['final']) : '…' }}</td>
                        @if ($hasReexam)<td class="fw-bold">{{ isset($subject['reexam']) ? $fmt($subject['reexam']) : '' }}</td>@endif
                        <td>
                            @if ($subject['passed'] === true) {{ __('enums.final_result.passed') }}
                            @elseif ($subject['passed'] === false) <b>{{ __('enums.final_result.failed') }}</b>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <table class="report-card-summary">
                <tr>
                    <th>{{ __('exams.total') }}</th><td>{{ $fmt($card['total']) }}</td>
                    <th>{{ __('exams.average') }}</th><td>{{ $fmt($card['average']) }}</td>
                    <th>{{ __('exams.result') }}</th><td class="fw-bold">{{ $result?->label() ?? __('exams.incomplete') }}@if ($hasReexam) <span class="small fw-normal">({{ __('exams.after_reexam') }})</span>@endif</td>
                    <th>{{ __('exams.grade') }}</th><td class="fw-bold">{{ ! empty($card['grade']) ? __('exams.grades.'.$card['grade']) : '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('exams.attendance_days') }}</th>
                    <td colspan="7">
                        {{ __('enums.attendance_status.present') }}: @fa($card['attendance']['present'] + $card['attendance']['late'])
                        · {{ __('enums.attendance_status.absent') }}: @fa($card['attendance']['absent'])
                        · {{ __('enums.attendance_status.excused') }}: @fa($card['attendance']['excused'])
                        · {{ __('enums.attendance_status.sick') }}: @fa($card['attendance']['sick'])
                    </td>
                </tr>
            </table>
            <div class="small text-secondary">
                {{ __('exams.card_rules', [
                    'pass' => $fmt($card['settings']['pass_percent']),
                    'a' => $fmt($card['settings']['grade_a_min'] ?? \App\Models\Setting::get('grade_a_min', 90)),
                    'b' => $fmt($card['settings']['grade_b_min'] ?? \App\Models\Setting::get('grade_b_min', 75)),
                    'c' => $fmt($card['settings']['grade_c_min'] ?? \App\Models\Setting::get('grade_c_min', 60)),
                ]) }}
            </div>

            <div class="signatures">
                <div>{{ __('app.signature_teacher') }}@if ($section->classTeacher)<div class="small">{{ $section->classTeacher->full_name }}</div>@endif</div>
                <div>{{ __('app.signature_principal') }}@if ($p = \App\Models\Setting::get('principal_name'))<div class="small">{{ $p }}</div>@endif</div>
            </div>
        </div>
    @empty
        <p class="text-center text-secondary">{{ __('academics.no_students_in_section') }}</p>
    @endforelse
@endsection
