@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('labs.reports_title')])

@php
    $t = 'labs.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) (int) $v);
    $q = fn ($v) => \App\Support\Quantity::format($v);
    $m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents));
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __($t.'reports_title') }}@if ($year) — {{ __('academics.year') }} @fa($year->name)@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __($t.'reports_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
            <a href="{{ route('labs.reports.index', ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('app.print') }}</a>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'reports_intro') }}</div>
        @if ($pending)
            <div class="alert alert-warning small"><a href="{{ route('labs.activities.index', ['status' => 'pending']) }}">{{ __($t.'pending_warning', ['count' => $n($pending)]) }}</a></div>
        @endif
    @endif

    <div @class(['card mb-3' => ! $print, 'mb-3' => $print])>
        <div @class(['card-header fw-medium' => ! $print, 'fw-bold mb-1' => $print])>{{ __($t.'per_lab') }}</div>
        <div class="table-responsive">
            <table @class(['table table-sm align-middle mb-0', 'table-bordered' => $print])>
                <thead><tr><th>{{ __($t.'lab') }}</th><th class="text-center">{{ __($t.'items_count') }}</th><th class="text-center">{{ __($t.'value') }}</th><th class="text-center">{{ __($t.'alerts.low') }}</th><th class="text-center">{{ __($t.'alerts.expired') }}</th><th class="text-center">{{ __($t.'alerts.damaged') }}</th><th class="text-center">{{ __($t.'activities_done') }}</th></tr></thead>
                <tbody>
                @forelse ($perLab as $r)
                    <tr @class(['text-secondary' => ! $r['lab']->is_active])>
                        <td>{{ $r['lab']->name }} <span class="small text-secondary">({{ $r['lab']->type->label() }})</span></td>
                        <td class="text-center">{{ $n($r['items']) }}</td>
                        <td class="text-center">{{ $m($r['value']) }}</td>
                        <td class="text-center">{{ $n($r['low']) }}</td>
                        <td class="text-center">{{ $n($r['expired']) }}</td>
                        <td class="text-center">{{ $n($r['damaged']) }}</td>
                        <td class="text-center">{{ $n($r['done']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-3">{{ __($t.'no_labs') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            @foreach (['expired' => $expired, 'expiring' => $expiring, 'low' => $low, 'damaged' => $damaged] as $key => $list)
                <div @class(['card mb-3' => ! $print, 'mb-3' => $print])>
                    <div @class(['card-header fw-medium' => ! $print, 'fw-bold mb-1' => $print])>{{ __($t.'list_'.$key) }} ({{ $n($list->count()) }})</div>
                    @if ($list->isEmpty())
                        <div class="card-body small text-secondary py-2">{{ __($t.'none_here') }}</div>
                    @else
                        <ul class="list-group list-group-flush small">
                            @foreach ($list as $i)
                                <li class="list-group-item d-flex gap-2">
                                    <span class="flex-grow-1">@if ($print){{ $i->name }}@else<a href="{{ route('labs.items.show', $i) }}">{{ $i->name }}</a>@endif <span class="text-secondary">— {{ $i->lab->name }}</span></span>
                                    <span class="text-nowrap">
                                        @if (in_array($key, ['expired', 'expiring'], true))@jdate($i->expires_on)
                                        @elseif ($key === 'damaged'){{ $q($i->damaged_quantity) }} {{ $i->unit->label() }}
                                        @else{{ $q($i->quantity) }} / {{ $q($i->min_quantity) }} {{ $i->unit->label() }}@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="col-lg-6">
            <div @class(['card mb-3' => ! $print, 'mb-3' => $print])>
                <div @class(['card-header fw-medium' => ! $print, 'fw-bold mb-1' => $print])>{{ __($t.'top_consumption') }}</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($consumption as $r)
                        <li class="list-group-item d-flex gap-2">
                            <span class="flex-grow-1">{{ $r['item']->name }} <span class="text-secondary">— {{ $r['item']->lab->name }}</span></span>
                            <span>{{ $q($r['total']) }} {{ $r['item']->unit->label() }} · {{ __($t.'times_n', ['count' => $n($r['times'])]) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">{{ __($t.'none_here') }}</li>
                    @endforelse
                </ul>
            </div>

            <div @class(['card mb-3' => ! $print, 'mb-3' => $print])>
                <div @class(['card-header fw-medium' => ! $print, 'fw-bold mb-1' => $print])>{{ __($t.'by_subject') }}</div>
                <div class="table-responsive">
                    <table @class(['table table-sm align-middle mb-0', 'table-bordered' => $print])>
                        <thead><tr><th>{{ __('academics.section') }}</th><th>{{ __('academics.subject') }}</th><th class="text-center">{{ __($t.'activities_done') }}</th></tr></thead>
                        <tbody>
                        @forelse ($bySubject as $r)
                            <tr><td>{{ $r->section->gradeLevel->name }} {{ $r->section->name }}</td><td>{{ $r->subject->name }}</td><td class="text-center">{{ $n($r->n) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-3">{{ __($t.'none_here') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
