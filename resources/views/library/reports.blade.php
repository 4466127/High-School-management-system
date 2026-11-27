@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('library.reports_title')])

@php
    $t = 'library.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) (int) $v);
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __($t.'reports_title') }} — {{ \App\Http\Controllers\Library\ReportController::libraryName() }}@if ($year) — {{ __('academics.year') }} @fa($year->name)@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __($t.'reports_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('library.reports.index', ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('app.print') }}</a>
                <a href="{{ route('library.loans.index', ['status' => 'overdue']) }}" class="btn btn-outline-danger"><i class="fa-solid fa-clock" aria-hidden="true"></i> {{ __($t.'overdue_list') }}</a>
                <a href="{{ route('library.catalog.export') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __($t.'catalog_csv') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'reports_intro') }}</div>
    @endif

    <div class="row g-2 mb-3">
        @foreach ([
            ['fa-arrow-right-arrow-left', __($t.'stat.loans_year'), $n($loansThisYear), __($t.'stat.staff_share', ['count' => $n($staffLoans)])],
            ['fa-clock', __($t.'stat.overdue'), $n($overdue), null],
            ['fa-triangle-exclamation', __($t.'stat.lost_damaged'), $n(($statusCounts['lost'] ?? 0) + ($statusCounts['damaged'] ?? 0)), __($t.'stat.withdrawn', ['count' => $n($statusCounts['withdrawn'] ?? 0)])],
            ['fa-coins', __($t.'stat.value'), $value, __($t.'stat.value_hint')],
        ] as [$icon, $label, $value2, $sub])
            <div class="col-6 col-lg-3">
                <div class="card card-body py-2 h-100">
                    <div class="small text-secondary"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
                    <div class="fs-5 fw-bold">{{ $value2 }}</div>
                    @if ($sub)<div class="small text-secondary">{{ $sub }}</div>@endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header fw-medium"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ __($t.'stock_by_category') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __($t.'category') }}</th><th class="text-center">{{ __($t.'titles') }}</th><th class="text-center">{{ __($t.'copies') }}</th><th class="text-center">{{ __($t.'available') }}</th><th class="text-center">{{ __($t.'on_loan') }}</th></tr></thead>
                        <tbody>
                        @php($sum = ['titles' => $byCategory->sum('titles'), 'copies' => $byCategory->sum('copies'), 'available' => $byCategory->sum('available'), 'on_loan' => $byCategory->sum('on_loan')])
                        @foreach (\App\Enums\BookCategory::cases() as $cat)
                            @php($r = $byCategory->get($cat->value))
                            @continue(! $r)
                            <tr><td>{{ $cat->label() }}</td><td class="text-center">{{ $n($r->titles) }}</td><td class="text-center">{{ $n($r->copies) }}</td><td class="text-center">{{ $n($r->available) }}</td><td class="text-center">{{ $n($r->on_loan) }}</td></tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr class="fw-bold"><td>{{ __($t.'total') }}</td>@foreach ($sum as $v)<td class="text-center">{{ $n($v) }}</td>@endforeach</tr></tfoot>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-medium"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ __($t.'problem_copies') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'title') }}</th><th>{{ __($t.'status') }}</th><th>{{ __($t.'reason') }}</th></tr></thead>
                        <tbody>
                        @forelse ($problems as $c)
                            <tr>
                                <td>@fa($c->accession_no)</td>
                                <td>@if ($print){{ $c->book->title }}@else<a href="{{ route('library.catalog.show', $c->book) }}">{{ $c->book->title }}</a>@endif</td>
                                <td><span class="badge {{ $c->status->badgeClass() }}">{{ $c->status->label() }}</span><div class="small text-secondary">{{ $c->status_changed_on ? \App\Support\JalaliDate::format($c->status_changed_on) : '' }}</div></td>
                                <td class="small">{{ $c->status_reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-3">{{ __($t.'no_problems') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header fw-medium"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ __($t.'top_books') }}</div>
                <ol class="list-group list-group-flush list-group-numbered">
                    @forelse ($top as $r)
                        <li class="list-group-item d-flex gap-2 small">
                            <span class="flex-grow-1">{{ $r['book']->title }}@if ($r['book']->author) <span class="text-secondary">— {{ $r['book']->author }}</span>@endif</span>
                            <span class="badge text-bg-light border">{{ __($t.'times_n', ['count' => $n($r['count'])]) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item small text-secondary">{{ __($t.'no_loans_year') }}</li>
                    @endforelse
                </ol>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-medium"><i class="fa-solid fa-chalkboard" aria-hidden="true"></i> {{ __($t.'reading_by_section') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('academics.section') }}</th><th class="text-center">{{ __($t.'loans_count') }}</th><th class="text-center">{{ __($t.'readers') }}</th></tr></thead>
                        <tbody>
                        @forelse ($bySection as $r)
                            <tr><td>{{ $r->section->label }}</td><td class="text-center">{{ $n($r->loans) }}</td><td class="text-center">{{ $n($r->readers) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-3">{{ __($t.'no_loans_year') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-secondary">{{ __($t.'reading_hint') }}</div>
            </div>
        </div>
    </div>
@endsection
