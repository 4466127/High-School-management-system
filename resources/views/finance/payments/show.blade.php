@extends('layouts.print', ['title' => __('finance.receipt').' '.$payment->receipt_no])

@section('content')
    <div class="receipt">
        <h1 class="report-card-title">
            {{ __('finance.receipt_title') }} — {{ __('finance.receipt_no') }} @fa($payment->receipt_no)
            @if ($payment->isVoided())<span class="report-card-provisional">{{ __('finance.voided') }}</span>@endif
        </h1>

        <table class="report-card-info">
            <tr>
                <th>{{ __('students.full_name') }}</th><td>{{ $payment->student->full_name }}</td>
                <th>{{ __('students.father_name') }}</th><td>{{ $payment->student->father_name }}</td>
            </tr>
            <tr>
                <th>{{ __('students.admission_no') }}</th><td>@fa($payment->student->admission_no)</td>
                <th>{{ __('finance.paid_on') }}</th><td>@jdate($payment->paid_on, 'long')</td>
            </tr>
            @if ($payment->payer_name)
                <tr><th>{{ __('finance.payer') }}</th><td colspan="3">{{ $payment->payer_name }}</td></tr>
            @endif
        </table>

        <table class="table table-bordered table-sm align-middle">
            <thead>
            <tr>
                <th>{{ __('finance.fee_type') }}</th>
                <th>{{ __('finance.period_label') }}</th>
                <th class="text-end">{{ __('finance.amount') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->invoice->feeType->name }}</td>
                    <td>{{ $allocation->invoice->period_label }}</td>
                    <td class="text-end">{{ \App\Support\Money::format($allocation->amount) }}</td>
                </tr>
            @endforeach
            <tr class="fw-bold">
                <td colspan="2">{{ __('finance.total') }}</td>
                <td class="text-end">{{ \App\Support\Money::format($payment->amount) }}</td>
            </tr>
            </tbody>
        </table>

        <div class="small">
            {{ __('finance.receipt_remaining') }}: <b>{{ \App\Support\Money::format(\App\Support\Money::fromCents($remaining)) }}</b>
            · {{ __('finance.received_by') }}: {{ $payment->receiver?->name }}
            @if ($payment->note)<div>{{ __('finance.note') }}: {{ $payment->note }}</div>@endif
            @if ($payment->isVoided())
                <div class="text-danger fw-bold">{{ __('finance.voided_by', ['name' => $payment->voidedBy?->name, 'reason' => $payment->void_reason]) }}</div>
            @endif
        </div>

        <div class="signatures">
            <div>{{ __('finance.signature_receiver') }}</div>
            <div>{{ __('finance.signature_payer') }}</div>
        </div>
    </div>

    @if (! $payment->isVoided() && auth()->user()->can('finance.manage'))
        <form method="POST" action="{{ route('finance.payments.void', $payment) }}" class="noprint mt-4 card card-body" data-confirm="{{ __('finance.confirm_void_payment') }}">
            @csrf
            @method('PATCH')
            <label for="void-reason" class="form-label">{{ __('finance.void_reason') }}</label>
            <div class="d-flex gap-2">
                <input type="text" id="void-reason" name="reason" required maxlength="500" class="form-control">
                <button type="submit" class="btn btn-outline-danger text-nowrap">{{ __('finance.void') }}</button>
            </div>
        </form>
    @endif
    <div class="noprint mt-2"><a href="{{ route('students.show', $payment->student) }}#fees" class="btn btn-light">{{ __('app.back') }}</a></div>
@endsection
