@extends('layouts.print', ['title' => $tpl['title'], 'noHeader' => true])

@php
    $fmt = fn ($n) => $n === null ? '' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n));
    $sample ??= false;
@endphp

@section('content')
    @if ($sample)<div class="alert alert-warning noprint small">{{ __('exam_templates.sample_notice') }}</div>@endif
    @forelse ($items as $item)
        @php($student = $item['student'])
        @php($data = $item['data'])
        @php($result = $data['result'] ? \App\Enums\FinalResult::tryFrom($data['result']) : null)
        <div class="transcript">
            @include('layouts.partials.letterhead')
            <h1 class="report-card-title">{{ $tpl['title'] }}</h1>

            <table class="report-card-info">
                <tr>
                    <th>{{ __('students.full_name') }}</th><td>{{ $student->full_name }}</td>
                    <th>{{ __('students.father_name') }}</th><td>{{ $student->father_name }}</td>
                    <th>{{ __('students.grandfather_name') }}</th><td>{{ $student->grandfather_name ?: '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('students.admission_no') }}</th><td>@fa($student->admission_no)</td>
                    <th>{{ __('students.birth_date') }}</th><td>{{ $student->birth_date ? \App\Support\JalaliDate::format($student->birth_date) : '—' }}</td>
                    <th>{{ __('students.status') }}</th>
                    <td>{{ $student->status?->label() ?? '—' }}@if ($student->certificate_no) — {{ __('archive.certificate_no') }}: @fa($student->certificate_no)@endif</td>
                </tr>
            </table>

            <table class="table table-bordered table-sm align-middle text-center transcript-marks">
                <thead>
                <tr>
                    <th rowspan="2" style="width: 30px">#</th>
                    <th rowspan="2" class="text-start">{{ __('academics.subject') }}</th>
                    @foreach ($data['columns'] as $col)
                        <th>{{ $col['level']->name }}
                            <div class="transcript-src">
                                @if ($col['year'] !== ''){{ __('academics.year') }} @fa($col['year'])@endif
                                @if ($col['school']) — {{ $col['school'] }}@endif
                            </div>
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($data['columns'] as $col)
                        <th class="transcript-src">
                            @if ($col['source'] === 'pending') {{ __('transcripts.pending') }}
                            @elseif ($col['source'] === 'missing') {{ __('transcripts.missing') }}
                            @else {{ __('transcripts.mark_of_100') }}
                            @endif
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($data['rows'] as $i => $row)
                    <tr>
                        <td>@fa($i + 1)</td>
                        <td class="text-start">{{ $row['name'] }}</td>
                        @foreach ($row['marks'] as $c => $mark)
                            <td @class(['fw-bold' => $row['passed'][$c] === false])>{{ $fmt($mark) }}@if ($row['passed'][$c] === false) *@endif</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 2 + count($data['columns']) }}" class="text-secondary py-3">{{ __('transcripts.no_results') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="2" class="text-start">{{ __('exams.total') }}</th>
                    @foreach ($data['columns'] as $col)<td>{{ $fmt($col['total']) }}</td>@endforeach
                </tr>
                <tr>
                    <th colspan="2" class="text-start">{{ __('exams.average') }}</th>
                    @foreach ($data['columns'] as $col)<td class="fw-bold">{{ $fmt($col['average']) }}</td>@endforeach
                </tr>
                <tr>
                    <th colspan="2" class="text-start">{{ __('exams.result') }}</th>
                    @foreach ($data['columns'] as $col)<td>{{ $col['result'] ? \App\Services\StudentRecordService::resultLabel($col['result']) : '' }}</td>@endforeach
                </tr>
                <tr>
                    <th colspan="2" class="text-start">{{ __('exams.grade') }}</th>
                    @foreach ($data['columns'] as $col)<td>{{ $col['grade'] ? __('exams.grades.'.$col['grade']) : '' }}</td>@endforeach
                </tr>
                </tfoot>
            </table>

            <table class="report-card-summary">
                <tr>
                    <th>{{ __('transcripts.overall_total') }}</th><td>{{ $fmt($data['total']) ?: '—' }}</td>
                    <th>{{ __('transcripts.overall_average') }}</th><td class="fw-bold">{{ $fmt($data['average']) ?: '—' }}</td>
                    <th>{{ __('transcripts.overall_result') }}</th><td class="fw-bold">{{ $result?->label() ?? __('transcripts.incomplete') }}</td>
                </tr>
            </table>
            <div class="small text-secondary">{{ __('transcripts.legend') }}</div>

            <div class="signatures">
                @foreach ($tpl['signatures'] as $label)
                    <div>{{ $label }}@if ($loop->first && ($p = \App\Models\Setting::get('principal_name')))<div class="small">{{ $p }}</div>@endif</div>
                @endforeach
                @if ($tpl['stamp'])<div class="signature-stamp">{{ __('letters.stamp') }}</div>@endif
            </div>
            @if ($tpl['footer'])<div class="report-card-footer">{{ $tpl['footer'] }}</div>@endif
        </div>
    @empty
        <p class="text-center text-secondary">{{ __('academics.no_students_in_section') }}</p>
    @endforelse
@endsection
