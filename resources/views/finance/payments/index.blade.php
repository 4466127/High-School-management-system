@extends('layouts.app', ['title' => __('finance.payments_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('finance.payments_title') }}</h1>
        <a href="{{ route('finance.payments.export', request()->only('from', 'to', 'q')) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
    </div>

    @can('finance.manage')
        <form method="GET" class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> {{ __('finance.new_payment') }}</div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <input type="search" name="student_q" value="{{ request('student_q') }}" class="form-control" placeholder="{{ __('finance.find_student') }}" aria-label="{{ __('finance.find_student') }}" autofocus>
                    <button type="submit" class="btn btn-primary text-nowrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('finance.find') }}</button>
                </div>
                @if (request()->filled('student_q'))
                    <div class="list-group mt-2">
                        @forelse ($found as $student)
                            <a href="{{ route('finance.payments.create', $student) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><b>{{ $student->full_name }}</b> <span class="small text-secondary">— {{ __('students.father_name') }}: {{ $student->father_name }} · @fa($student->admission_no)</span></span>
                                <span class="small text-secondary">{{ $student->currentEnrollment?->section?->shortLabel }}</span>
                            </a>
                        @empty
                            <div class="list-group-item text-secondary">{{ __('app.no_records') }}</div>
                        @endforelse
                    </div>
                @endif
            </div>
        </form>
    @endcan

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-sm-4"><label class="form-label" for="p-q">{{ __('app.search') }}</label><input type="search" id="p-q" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('finance.search_receipts') }}"></div>
            <div class="col-sm-3"><label class="form-label" for="p-from">{{ __('app.from_date') }}</label><input type="text" id="p-from" name="from" value="{{ $from }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            <div class="col-sm-3"><label class="form-label" for="p-to">{{ __('app.to_date') }}</label><input type="text" id="p-to" name="to" value="{{ $to }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            <div class="col-sm-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
        </div>
    </form>

    <div class="small fw-medium mb-2">{{ __('finance.receipts_total', ['amount' => \App\Support\Money::format(\App\Support\Money::fromCents($total))]) }}</div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('finance.receipt_no') }}</th>
                    <th>{{ __('finance.paid_on') }}</th>
                    <th>{{ __('students.full_name') }}</th>
                    <th class="text-end">{{ __('finance.amount') }}</th>
                    <th>{{ __('finance.received_by') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($payments as $payment)
                    <tr @class(['text-secondary text-decoration-line-through' => $payment->isVoided()])>
                        <td class="fw-bold"><a href="{{ route('finance.payments.show', $payment) }}" class="text-decoration-none">@fa($payment->receipt_no)</a></td>
                        <td class="small">@jdate($payment->paid_on)</td>
                        <td>{{ $payment->student->full_name }}</td>
                        <td class="text-end fw-medium">{{ \App\Support\Money::format($payment->amount) }}</td>
                        <td class="small">{{ $payment->receiver?->name }}</td>
                        <td>@if ($payment->isVoided())<span class="badge text-bg-secondary">{{ __('finance.voided') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('finance.no_payments') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $payments->links() }}</div>
@endsection
