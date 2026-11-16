@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('reports.finance_title')])

@php
    $m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents));
    $school = $summary['school'];
    $courses = $summary['courses'];
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('reports.finance_title') }}</h1>
        <div class="text-center small mb-3">@jdate($from) ← @jdate($to)</div>
    @else
        @include('reports._filters', ['title' => __('reports.finance_title'), 'route' => 'reports.finance.index', 'export' => 'reports.finance.export', 'dates' => true])
        <p class="small text-secondary">{{ __('reports.finance_hint') }}</p>
    @endif

    @if (! $school && ! $courses)
        <div class="card card-body text-center text-secondary py-5">{{ __('reports.finance_nothing') }}</div>
    @else
        <div class="row g-3">
            <div class="col-lg-6">
                <div @class(['card h-100' => ! $print])>
                    <div class="card-header fw-medium"><i class="fa-solid fa-school" aria-hidden="true"></i> {{ __('reports.school_part') }}</div>
                    @if ($school)
                        <table class="table align-middle mb-0 @if ($print) table-bordered table-sm @endif">
                            <tbody>
                            @if ($feesEnabled)<tr><td>{{ __('finance.sum_fees') }}</td><td class="text-end text-success fw-medium">{{ $m($school['fees']) }}</td></tr>@endif
                            <tr><td>{{ __('finance.sum_income') }}</td><td class="text-end text-success fw-medium">{{ $m($school['income']) }}</td></tr>
                            <tr><td>{{ __('finance.sum_expense') }}</td><td class="text-end text-danger fw-medium">{{ $m($school['expense']) }}</td></tr>
                            <tr class="fw-bold"><td>{{ __('finance.sum_balance') }}</td><td @class(['text-end', 'text-danger' => $school['balance'] < 0])>{{ $m($school['balance']) }}</td></tr>
                            @if ($feesEnabled)
                                <tr><td>{{ __('reports.debt_total') }}</td><td class="text-end">{{ $m($school['debt']) }}</td></tr>
                                <tr><td>{{ __('reports.debtors_count') }}</td><td class="text-end">@fa($school['debtors'])</td></tr>
                            @endif
                            </tbody>
                        </table>
                    @else
                        <div class="card-body small text-secondary">{{ __('reports.school_off') }}</div>
                    @endif
                </div>
            </div>
            @if ($courses)
                <div class="col-lg-6">
                    <div @class(['card h-100' => ! $print])>
                        <div class="card-header fw-medium"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> {{ __('reports.courses_part') }}</div>
                        <table class="table align-middle mb-0 @if ($print) table-bordered table-sm @endif">
                            <tbody>
                            <tr><td>{{ __('courses.sum_donations_cashbox') }}</td><td class="text-end text-success fw-medium">{{ $m($courses['donations_cashbox']) }}</td></tr>
                            <tr><td>{{ __('courses.sum_donations_direct') }}</td><td class="text-end">{{ $m($courses['donations_direct']) }}</td></tr>
                            <tr><td>{{ __('courses.sum_salaries') }}</td><td class="text-end text-primary fw-medium">{{ $m($courses['salaries']) }}</td></tr>
                            <tr><td>{{ __('courses.sum_expenses') }}</td><td class="text-end text-danger fw-medium">{{ $m($courses['expenses']) }}</td></tr>
                            <tr class="fw-bold"><td>{{ __('reports.course_cash_now') }}</td><td @class(['text-end', 'text-danger' => $courses['cash_balance'] < 0])>{{ $m($courses['cash_balance']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
        @if ($print)
            <div class="signatures"><div>{{ __('app.signature_principal') }}</div><div>{{ __('finance.recorded_by') }}</div></div>
        @endif
    @endif
@endsection
