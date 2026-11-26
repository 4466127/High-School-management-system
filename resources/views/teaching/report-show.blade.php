@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('teaching.reports_title')])

@php
    $r = $report;
    $n = fn ($v) => $v === null ? '—' : \App\Support\Digits::toPersian((string) $v);
    $pct = fn ($v) => $v === null ? '—' : \App\Support\Digits::toPersian((string) $v).'٪';
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('teaching.report_of', ['name' => $r['teacher']->full_name]) }} — {{ __('academics.year') }} @fa($year->name)</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('teaching.report_of', ['name' => $r['teacher']->full_name]) }} <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('teaching.reports.show', [$r['teacher'], 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('timetable.teachers.index', ['staff' => $r['teacher']->id]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-table-cells" aria-hidden="true"></i> {{ __('timetable.teacher_title') }}</a>
                <a href="{{ route('teaching.reports.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
    @endif

    <div class="row g-2 mb-3">
        @foreach ([
            'subjects' => $n($r['subjects']), 'hours' => $n($r['hours']),
            'attendance' => $n($r['present']).' / '.$n($r['absent']).' / '.$n($r['leave']),
            'marks' => $pct($r['marks_percent']), 'progress' => $pct($r['plan_percent']),
        ] as $key => $value)
            <div class="col-6 col-md">
                <div class="card card-body py-2 text-center">
                    <div class="small text-secondary">{{ __('teaching.col.'.$key) }}</div>
                    <div class="fs-5 fw-bold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-bordered table-sm' => $print])>
                <thead><tr>
                    <th>{{ __('academics.section') }}</th><th>{{ __('academics.subject') }}</th>
                    <th class="text-center">{{ __('teaching.col.hours') }}</th><th class="text-center">{{ __('teaching.col.marks') }}</th>
                    <th>{{ __('teaching.status') }}</th><th class="text-center">{{ __('teaching.col.progress') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($r['rows'] as $row)
                    <tr>
                        <td>{{ $row['section']->label }}</td>
                        <td class="fw-medium">{{ $row['subject']->name }}</td>
                        <td class="text-center">{{ $n($row['hours']) }}</td>
                        <td class="text-center">{{ $pct($row['marks_percent']) }}<div class="small text-secondary">@fa($row['marks_entered']) / @fa($row['marks_expected'])</div></td>
                        <td>
                            @if ($row['plan'])
                                @if ($print){{ $row['plan']->status->label() }}@else<a href="{{ route('teaching.plans.show', $row['plan']) }}" class="badge {{ $row['plan']->status->badgeClass() }} text-decoration-none">{{ $row['plan']->status->label() }}</a>@endif
                            @else<span class="small text-secondary">{{ __('teaching.no_plan') }}</span>@endif
                        </td>
                        <td class="text-center">
                            {{ $row['progress'] ? $pct($row['progress']['percent']) : '—' }}
                            @if ($row['progress'] && $row['progress']['overdue'])<div class="small text-danger">{{ __('teaching.overdue_n', ['count' => $n($row['progress']['overdue'])]) }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('teaching.no_assignments_teacher') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="small text-secondary mt-2">{{ __('teaching.reports_legend') }}</div>
@endsection
