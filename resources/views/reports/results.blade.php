@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('reports.results_title')])

@php
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $fmt = fn ($v) => $v === null ? '—' : $n(\App\Services\MarkService::format($v));
    $results = \App\Enums\FinalResult::cases();
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('reports.results_title') }}</h1>
        <div class="text-center small mb-3">{{ $section?->label ?? __('reports.all_sections') }}@if ($year) · {{ __('academics.year') }} @fa($year->name)@endif</div>
    @else
        @include('reports._filters', ['title' => __('reports.results_title'), 'route' => 'reports.results.index', 'export' => 'reports.results.export'])
        <p class="small text-secondary">{{ __('reports.results_hint') }}</p>
    @endif

    @if ($rows->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ $year ? __('reports.no_data') : __('academics.no_year') }}</div>
    @else
        <div @class(['card mb-3' => ! $print, 'mb-3' => $print])>
            <div class="table-responsive">
                <table class="table table-sm align-middle text-center mb-0 @if ($print) table-bordered @endif">
                    <thead>
                    <tr>
                        <th class="text-start">{{ __('academics.section') }}</th>
                        <th>{{ __('reports.total') }}</th>
                        @foreach ($results as $r)<th>{{ $r->label() }}</th>@endforeach
                        <th>{{ __('exams.incomplete') }}</th>
                        <th>{{ __('reports.pass_rate') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-start">@if ($print){{ $row['section']->label }}@else<a href="{{ route('exams.results.index', ['section' => $row['section']->id]) }}" class="text-decoration-none">{{ $row['section']->label }}</a>@endif</td>
                            <td>{{ $n($row['total']) }}</td>
                            @foreach ($results as $r)<td>{{ $n($row['counts'][$r->value]) }}</td>@endforeach
                            <td class="text-secondary">{{ $n($row['counts']['incomplete']) }}</td>
                            <td class="fw-bold">{{ $row['pass_rate'] !== null ? $n($row['pass_rate']).'٪' : '—' }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $row['finalized'], 'text-bg-light border' => ! $row['finalized']])>{{ $row['finalized'] ? __('reports.finalized') : __('reports.not_finalized') }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td class="text-start">{{ __('reports.grand_total') }}</td>
                        <td>{{ $n($rows->sum('total')) }}</td>
                        @foreach ($results as $r)<td>{{ $n($rows->sum(fn ($row) => $row['counts'][$r->value])) }}</td>@endforeach
                        <td>{{ $n($rows->sum(fn ($row) => $row['counts']['incomplete'])) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div @class(['card' => ! $print])>
            <div class="card-header fw-medium">{{ __('reports.failed_list') }} ({{ $n($students->count()) }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 @if ($print) table-bordered @endif">
                    <thead><tr><th>{{ __('academics.section') }}</th><th>{{ __('academics.roll_no') }}</th><th>{{ __('students.full_name') }}</th><th>{{ __('students.father_name') }}</th><th>{{ __('exams.result') }}</th><th>{{ __('exams.average') }}</th><th>{{ __('reports.failed_subjects') }}</th></tr></thead>
                    <tbody>
                    @forelse ($students as $s)
                        <tr>
                            <td class="small">{{ $s['section']->label }}</td>
                            <td>@fa($s['enrollment']->roll_no)</td>
                            <td class="fw-medium">{{ $s['enrollment']->student->full_name }}</td>
                            <td>{{ $s['enrollment']->student->father_name }}</td>
                            <td><span class="badge {{ $s['result']->badgeClass() }}">{{ $s['result']->label() }}</span>@if ($s['provisional']) <span class="small text-secondary">({{ __('exams.provisional') }})</span>@endif</td>
                            <td>{{ $fmt($s['average']) }}</td>
                            <td class="small">{{ implode('، ', $s['failed_subjects']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-3">{{ __('reports.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
