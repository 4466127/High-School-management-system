@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('reports.attendance_title')])

@php
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $statuses = \App\Enums\AttendanceStatus::cases();
    $sum = [];
    foreach ($rows as $r) {
        foreach ($r['counts'] as $k => $v) {
            $sum[$k] = ($sum[$k] ?? 0) + $v;
        }
    }
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('reports.attendance_title') }}</h1>
        <div class="text-center small mb-3">@jdate($from) ← @jdate($to) · {{ $section?->label ?? __('reports.all_sections') }}</div>
    @else
        @include('reports._filters', ['title' => __('reports.attendance_title'), 'route' => 'reports.attendance.index', 'export' => 'reports.attendance.export', 'dates' => true])
        <p class="small text-secondary">{{ __('reports.attendance_hint') }}</p>
    @endif

    @if ($rows->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ $year ? __('reports.no_data') : __('academics.no_year') }}</div>
    @else
        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table class="table table-sm align-middle text-center mb-0 @if ($print) table-bordered @endif">
                    <thead>
                    <tr>
                        <th class="text-start">{{ __('academics.section') }}</th>
                        <th>{{ __('reports.days') }}</th>
                        @foreach ($statuses as $s)<th>{{ $s->label() }}</th>@endforeach
                        <th>{{ __('reports.attendance_percent') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="text-start">@if ($print){{ $r['section']->label }}@else<a href="{{ route('attendance.monthly.index', ['section' => $r['section']->id]) }}" class="text-decoration-none">{{ $r['section']->label }}</a>@endif</td>
                            <td>{{ $n($r['days']) }}</td>
                            @foreach ($statuses as $s)<td @class(['text-danger fw-bold' => $s->value === 'absent' && ($r['counts']['absent'] ?? 0) > 0])>{{ $n($r['counts'][$s->value] ?? 0) }}</td>@endforeach
                            <td @class(['fw-bold', 'text-danger' => $r['percent'] !== null && $r['percent'] < 75])>{{ $r['percent'] !== null ? $n($r['percent']).'٪' : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td class="text-start">{{ __('reports.grand_total') }}</td>
                        <td>{{ $n($rows->sum('days')) }}</td>
                        @foreach ($statuses as $s)<td>{{ $n($sum[$s->value] ?? 0) }}</td>@endforeach
                        @php($p = \App\Services\AttendanceService::percent($sum))
                        <td>{{ $p !== null ? $n($p).'٪' : '—' }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
@endsection
