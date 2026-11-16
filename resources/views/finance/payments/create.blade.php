@extends('layouts.app', ['title' => __('finance.pay_title', ['name' => $student->full_name])])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('finance.pay_title', ['name' => $student->full_name]) }}</h1>
        <a href="{{ route('students.show', $student) }}#fees" class="btn btn-light">{{ __('app.back') }}</a>
    </div>
    <div class="text-secondary small mb-3">
        {{ __('students.father_name') }}: {{ $student->father_name }} · {{ __('students.admission_no') }}: @fa($student->admission_no)
        @if ($student->currentEnrollment) · {{ $student->currentEnrollment->section->label }}@endif
    </div>

    @if ($invoices->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('finance.no_open_invoices') }}</div>
    @else
        @php($due = $invoices->sum('remaining_cents'))
        <form method="POST" action="{{ route('finance.payments.store', $student) }}" class="card form-section" novalidate>
            @csrf
            <div class="card-header"><i class="fa-solid fa-receipt" aria-hidden="true"></i> {{ __('finance.receive') }}</div>
            <div class="card-body">
                <p class="small text-secondary">{{ __('finance.pay_hint') }}</p>
                <div class="row">
                    <x-form.input class="col-md-4 mb-3" name="amount" :label="__('finance.amount').' ('.__('finance.afn').')'" :value="\App\Support\Money::plain(\App\Support\Money::fromCents($due))" required inputmode="decimal" ltr
                                  :help="__('finance.total_due', ['amount' => \App\Support\Money::format(\App\Support\Money::fromCents($due))])" />
                    <x-jalali-date class="col-md-4 mb-3" name="paid_on" :label="__('finance.paid_on')" :value="now()" required />
                    <x-form.input class="col-md-4 mb-3" name="payer_name" :label="__('finance.payer')" maxlength="150" :placeholder="__('finance.payer_ph')" />
                </div>
                <x-form.input name="note" :label="__('finance.note')" maxlength="500" />

                <div class="fw-bold mb-2">{{ __('finance.open_invoices') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('finance.fee_type') }}</th>
                            <th>{{ __('finance.period_label') }}</th>
                            <th class="text-end">{{ __('finance.net') }}</th>
                            <th class="text-end">{{ __('finance.paid') }}</th>
                            <th class="text-end">{{ __('finance.remaining') }}</th>
                            <th style="width: 160px">{{ __('finance.allocation') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->feeType->name }}</td>
                                <td>{{ $invoice->period_label }}</td>
                                <td class="text-end">{{ \App\Support\Money::format(\App\Support\Money::fromCents($invoice->netCents()), false) }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($invoice->paid, false) }}</td>
                                <td class="text-end fw-bold">{{ \App\Support\Money::format(\App\Support\Money::fromCents($invoice->remaining_cents), false) }}</td>
                                <td>
                                    <input type="text" name="allocations[{{ $invoice->id }}]" value="{{ old('allocations.'.$invoice->id) }}" inputmode="decimal" dir="ltr"
                                           class="form-control form-control-sm" placeholder="—" aria-label="{{ __('finance.allocation') }} — {{ $invoice->period_label }}">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <button type="submit" class="btn btn-success btn-lg"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('finance.receive') }}</button>
            </div>
        </form>
    @endif
@endsection
