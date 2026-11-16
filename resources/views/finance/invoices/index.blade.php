@extends('layouts.app', ['title' => __('finance.invoices_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('finance.invoices_title') }}</h1>
        @can('finance.manage')
            <a href="{{ route('finance.invoices.create') }}" class="btn btn-primary"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> {{ __('finance.issue_group') }}</a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label for="i-q" class="form-label">{{ __('app.search') }}</label>
                <input type="search" name="q" id="i-q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('finance.find_student') }}">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="i-section" class="form-label">{{ __('academics.section') }}</label>
                <select name="section" id="i-section" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($sections as $s)<option value="{{ $s->id }}" @selected((int) request('section') === $s->id)>{{ $s->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-4 col-lg-2">
                <label for="i-type" class="form-label">{{ __('finance.fee_type') }}</label>
                <select name="fee_type" id="i-type" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($types as $t)<option value="{{ $t->id }}" @selected((int) request('fee_type') === $t->id)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-4 col-lg-2">
                <label for="i-period" class="form-label">{{ __('finance.period_label') }}</label>
                <select name="period" id="i-period" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($periods as $p)<option value="{{ $p }}" @selected(\App\Support\Digits::toPersian((string) request('period')) === $p)>{{ $p }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-4 col-lg-2">
                <label for="i-status" class="form-label">{{ __('finance.status') }}</label>
                <select name="status" id="i-status" class="form-select">
                    @foreach (['open', 'paid', 'voided', 'all'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ __('finance.status_'.$s) }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-lg-auto"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
        </div>
    </form>

    <div class="small fw-medium mb-2">
        {{ __('finance.invoices_summary', [
            'count' => \App\Support\Digits::toPersian((string) $totals['count']),
            'net' => \App\Support\Money::format(\App\Support\Money::fromCents($totals['net'])),
            'paid' => \App\Support\Money::format(\App\Support\Money::fromCents($totals['paid'])),
            'remaining' => \App\Support\Money::format(\App\Support\Money::fromCents($totals['net'] - $totals['paid'])),
        ]) }}
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('students.full_name') }}</th>
                    <th>{{ __('academics.section') }}</th>
                    <th>{{ __('finance.fee_type') }}</th>
                    <th>{{ __('finance.period_label') }}</th>
                    <th class="text-end">{{ __('finance.net') }}</th>
                    <th class="text-end">{{ __('finance.paid') }}</th>
                    <th class="text-end">{{ __('finance.remaining') }}</th>
                    <th>{{ __('finance.status') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($invoices as $invoice)
                    @php($remaining = $invoice->isVoided() ? 0 : $invoice->netCents() - \App\Support\Money::toCents($invoice->paid))
                    <tr @class(['text-secondary' => $invoice->isVoided()])>
                        <td><a href="{{ route('students.show', $invoice->student) }}#fees" class="text-decoration-none fw-medium">{{ $invoice->student->full_name }}</a></td>
                        <td class="small">{{ $invoice->enrollment->section->shortLabel }}</td>
                        <td>{{ $invoice->feeType->name }}</td>
                        <td>{{ $invoice->period_label }}</td>
                        <td class="text-end">{{ \App\Support\Money::format(\App\Support\Money::fromCents($invoice->netCents()), false) }}
                            @if (\App\Support\Money::toCents($invoice->discount) > 0)<div class="small text-secondary">{{ __('finance.discount') }}: {{ \App\Support\Money::format($invoice->discount, false) }}</div>@endif
                        </td>
                        <td class="text-end">{{ \App\Support\Money::format($invoice->paid, false) }}</td>
                        <td class="text-end fw-bold">{{ \App\Support\Money::format(\App\Support\Money::fromCents($remaining), false) }}</td>
                        <td>
                            @if ($invoice->isVoided())<span class="badge text-bg-secondary">{{ __('finance.voided') }}</span>
                            @elseif ($remaining <= 0)<span class="badge text-bg-success">{{ __('finance.status_paid') }}</span>
                            @else<span class="badge text-bg-warning">{{ __('finance.remaining') }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">{{ __('finance.no_invoices') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
@endsection
