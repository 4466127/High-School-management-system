@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('teaching.reports_title')])

@php
    $n = fn ($v) => $v === null ? '—' : \App\Support\Digits::toPersian((string) $v);
    $pct = fn ($v) => $v === null ? '—' : \App\Support\Digits::toPersian((string) $v).'٪';
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('teaching.reports_title') }}@if ($year) — {{ __('academics.year') }} @fa($year->name)@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('teaching.reports_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('teaching.reports.index', ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('teaching.reports.export') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('teaching.reports_intro') }}</div>
    @endif

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @else
        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                    <thead><tr>
                        <th>{{ __('timetable.teacher') }}</th>
                        <th class="text-center">{{ __('teaching.col.subjects') }}</th>
                        <th class="text-center">{{ __('teaching.col.hours') }}</th>
                        <th class="text-center">{{ __('teaching.col.attendance') }}</th>
                        <th class="text-center">{{ __('teaching.col.marks') }}</th>
                        <th class="text-center">{{ __('teaching.col.plans') }}</th>
                        <th class="text-center">{{ __('teaching.col.progress') }}</th>
                        @unless ($print)<th></th>@endunless
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="fw-medium">{{ $r['teacher']->full_name }}</td>
                            <td class="text-center">{{ $n($r['subjects']) }}</td>
                            <td class="text-center">{{ $n($r['hours']) }}</td>
                            <td class="text-center small">
                                <span class="text-success">{{ $n($r['present']) }}</span> / <span class="text-danger">{{ $n($r['absent']) }}</span> / {{ $n($r['leave']) }}
                            </td>
                            <td @class(['text-center', 'text-danger' => $r['marks_percent'] !== null && $r['marks_percent'] < 100])>{{ $pct($r['marks_percent']) }}</td>
                            <td class="text-center small">{{ $n($r['plans']) }} / {{ $n($r['subjects']) }}@if ($r['plans']) <span class="text-secondary">({{ __('teaching.approved_n', ['count' => $n($r['plans_approved'])]) }})</span>@endif</td>
                            <td class="text-center">{{ $pct($r['plan_percent']) }}@if ($r['overdue']) <div class="small text-danger">{{ __('teaching.overdue_n', ['count' => $n($r['overdue'])]) }}</div>@endif</td>
                            @unless ($print)
                                <td class="text-end"><a href="{{ route('teaching.reports.show', $r['teacher']) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a></td>
                            @endunless
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">{{ __('timetable.no_teachers') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="small text-secondary mt-2">{{ __('teaching.reports_legend') }}</div>
    @endif
@endsection
