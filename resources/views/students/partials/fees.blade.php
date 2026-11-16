{{-- زبانه‌ی فیس پرونده‌ی دانش‌آموز: بل‌ها، رسیدها، صدور بل تکی، تخفیف و باطل کردن --}}
@php
    $money = fn ($v) => \App\Support\Money::format($v);
    $cents = fn (int $c) => \App\Support\Money::format(\App\Support\Money::fromCents($c));
    $manage = auth()->user()->can('finance.manage');
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span @class(['fw-bold fs-5', 'text-danger' => $fees['remaining'] > 0, 'text-success' => $fees['remaining'] <= 0])>{{ __('finance.student_balance', ['amount' => $cents($fees['remaining'])]) }}</span>
    @if ($manage)
        <div class="ms-auto d-flex gap-2">
            @if ($fees['canIssue'] && $fees['types']->isNotEmpty())
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#invoiceCreate"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> {{ __('finance.issue_single') }}</button>
            @endif
            @if ($fees['remaining'] > 0)
                <a href="{{ route('finance.payments.create', $student) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-receipt" aria-hidden="true"></i> {{ __('finance.receive') }}</a>
            @endif
        </div>
    @endif
</div>

<div class="card mb-3">
    <div class="card-header fw-bold">{{ __('finance.student_invoices') }}</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>{{ __('finance.fee_type') }}</th>
                <th>{{ __('finance.period_label') }}</th>
                <th class="text-end">{{ __('finance.amount') }}</th>
                <th class="text-end">{{ __('finance.discount') }}</th>
                <th class="text-end">{{ __('finance.paid') }}</th>
                <th class="text-end">{{ __('finance.remaining') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($fees['invoices'] as $invoice)
                <tr @class(['text-secondary text-decoration-line-through' => $invoice->isVoided()])>
                    <td>{{ $invoice->feeType->name }}</td>
                    <td>{{ $invoice->period_label }}</td>
                    <td class="text-end">{{ $money($invoice->amount) }}</td>
                    <td class="text-end">
                        {{ \App\Support\Money::toCents($invoice->discount) > 0 ? $money($invoice->discount) : '—' }}
                        @if ($invoice->discount_reason)<div class="small text-secondary">{{ $invoice->discount_reason }}</div>@endif
                    </td>
                    <td class="text-end">{{ $money($invoice->paid) }}</td>
                    <td class="text-end fw-bold">{{ $invoice->isVoided() ? __('finance.voided') : $cents($invoice->remaining_cents) }}</td>
                    <td class="text-end text-nowrap">
                        @if ($manage && ! $invoice->isVoided())
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#invDiscount{{ $invoice->id }}">{{ __('finance.set_discount') }}</button>
                            @if (\App\Support\Money::toCents($invoice->paid) === 0)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#invVoid{{ $invoice->id }}">{{ __('finance.void') }}</button>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-3">{{ __('finance.no_student_invoices') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($fees['payments']->isNotEmpty())
    <div class="card">
        <div class="card-header fw-bold">{{ __('finance.student_payments') }}</div>
        <ul class="list-group list-group-flush">
            @foreach ($fees['payments'] as $payment)
                <li @class(['list-group-item d-flex flex-wrap gap-2 align-items-center', 'text-secondary text-decoration-line-through' => $payment->isVoided()])>
                    <a href="{{ route('finance.payments.show', $payment) }}" class="fw-bold text-decoration-none">{{ __('finance.receipt') }} @fa($payment->receipt_no)</a>
                    <span class="small">@jdate($payment->paid_on)</span>
                    <span class="ms-auto fw-medium">{{ $money($payment->amount) }}</span>
                    @if ($payment->isVoided())<span class="badge text-bg-secondary">{{ __('finance.voided') }}</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($manage)
    @if ($fees['canIssue'] && $fees['types']->isNotEmpty())
        <x-modal-form id="invoiceCreate" :title="__('finance.issue_single')" :action="route('finance.invoices.single', $student)" :submit="__('finance.issue')">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="si-type" class="form-label">{{ __('finance.fee_type') }} <span class="text-danger">*</span></label>
                    <select name="fee_type_id" id="si-type" class="form-select" data-filter-target="#si-period" data-amount-target="#si-amount">
                        @foreach ($fees['types'] as $t)
                            <option value="{{ $t->id }}" @if (isset($fees['rates'][$t->id])) data-amount="{{ \App\Support\Money::plain($fees['rates'][$t->id]) }}" @endif>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="si-period" class="form-label">{{ __('finance.period_label') }} <span class="text-danger">*</span></label>
                    <select name="period_label" id="si-period" class="form-select">
                        @foreach ($fees['periods'] as $typeId => $periods)
                            @foreach ($periods as $p)<option value="{{ $p }}" data-type="{{ $typeId }}">{{ $p }}</option>@endforeach
                        @endforeach
                    </select>
                </div>
                @php($firstRate = $fees['rates'][$fees['types']->first()->id] ?? null)
                <x-form.input class="col-md-6 mb-3" id="si-amount" name="amount" :label="__('finance.amount')" :value="$firstRate ? \App\Support\Money::plain($firstRate) : null" required inputmode="decimal" ltr :use-old="old('_modal') === 'invoiceCreate'" />
                <x-jalali-date class="col-md-6 mb-3" name="due_date" :label="__('finance.due_date')" :use-old="old('_modal') === 'invoiceCreate'" />
                <x-form.input class="col-md-6 mb-3" name="discount" :label="__('finance.discount')" inputmode="decimal" ltr placeholder="0" :use-old="old('_modal') === 'invoiceCreate'" />
                <x-form.input class="col-md-6 mb-0" name="discount_reason" :label="__('finance.discount_reason')" maxlength="255" :placeholder="__('finance.discount_reason_ph')" :use-old="old('_modal') === 'invoiceCreate'" />
            </div>
        </x-modal-form>
    @endif

    @foreach ($fees['invoices']->reject->isVoided() as $invoice)
        <x-modal-form id="invDiscount{{ $invoice->id }}" :title="__('finance.set_discount').' — '.$invoice->feeType->name.' '.$invoice->period_label" :action="route('finance.invoices.discount', $invoice)" method="PATCH" :submit="__('app.save')">
            <x-form.input name="discount" :label="__('finance.discount')" :value="\App\Support\Money::plain($invoice->discount)" required inputmode="decimal" ltr :use-old="old('_modal') === 'invDiscount'.$invoice->id" />
            <x-form.input name="discount_reason" :label="__('finance.discount_reason')" :value="$invoice->discount_reason" maxlength="255" :placeholder="__('finance.discount_reason_ph')" :use-old="old('_modal') === 'invDiscount'.$invoice->id" />
        </x-modal-form>
        @if (\App\Support\Money::toCents($invoice->paid) === 0)
            <x-modal-form id="invVoid{{ $invoice->id }}" :title="__('finance.void').' — '.$invoice->feeType->name.' '.$invoice->period_label" :action="route('finance.invoices.void', $invoice)" method="PATCH" :submit="__('finance.void')" submit-class="btn-danger">
                <x-form.input name="reason" :label="__('finance.void_reason')" required maxlength="500" :use-old="old('_modal') === 'invVoid'.$invoice->id" />
            </x-modal-form>
        @endif
    @endforeach
@endif
