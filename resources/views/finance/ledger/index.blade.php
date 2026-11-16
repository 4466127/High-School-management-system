@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('finance.ledger_title')])

@php
    $m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents));
    $balance = $totals['income'] + ($feesEnabled ? $totals['fees'] : 0) - $totals['expense'];
    $query = array_filter($filters);
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('finance.ledger_title') }}@if ($filters['from'] || $filters['to']) — {{ $filters['from'] }} ← {{ $filters['to'] }}@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('finance.ledger_title') }}</h1>
            <div class="d-flex flex-wrap gap-2">
                @can('finance.manage')
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#txCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('finance.tx_create') }}</button>
                @endcan
                <a href="{{ route('finance.ledger.index', $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('finance.ledger.export', $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('finance.ledger_hint') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3"><div class="card card-body py-2"><div class="small text-secondary">{{ __('finance.sum_income') }}</div><div class="fw-bold text-success">{{ $m($totals['income']) }}</div></div></div>
        @if ($feesEnabled)
            <div class="col-6 col-lg-3"><div class="card card-body py-2"><div class="small text-secondary">{{ __('finance.sum_fees') }}</div><div class="fw-bold text-success">{{ $m($totals['fees']) }}</div></div></div>
        @endif
        <div class="col-6 col-lg-3"><div class="card card-body py-2"><div class="small text-secondary">{{ __('finance.sum_expense') }}</div><div class="fw-bold text-danger">{{ $m($totals['expense']) }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body py-2"><div class="small text-secondary">{{ __('finance.sum_balance') }}</div><div @class(['fw-bold', 'text-danger' => $balance < 0])>{{ $m($balance) }}</div></div></div>
    </div>

    @unless ($print)
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#transactions-pane" type="button" role="tab">{{ __('finance.tab_transactions') }}</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab">{{ __('finance.tab_categories') }}</button></li>
        </ul>
    @endunless

    <div class="tab-content">
        <div class="tab-pane fade show active" id="transactions-pane" role="tabpanel">
            @unless ($print)
                <form method="GET" class="card card-body mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-6 col-lg-2">
                            <label for="l-type" class="form-label">{{ __('finance.tx_type') }}</label>
                            <select name="type" id="l-type" class="form-select">
                                <option value="">{{ __('app.all') }}</option>
                                @foreach (\App\Enums\TransactionType::options() as $v => $l)<option value="{{ $v }}" @selected($filters['type'] === $v)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label for="l-cat" class="form-label">{{ __('finance.category') }}</label>
                            <select name="category" id="l-cat" class="form-select">
                                <option value="">{{ __('app.all') }}</option>
                                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($filters['category'] === $c->id)>{{ $c->type->label() }} — {{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-sm-4 col-lg-2"><label class="form-label" for="l-from">{{ __('app.from_date') }}</label><input type="text" id="l-from" name="from" value="{{ $filters['from'] }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
                        <div class="col-sm-4 col-lg-2"><label class="form-label" for="l-to">{{ __('app.to_date') }}</label><input type="text" id="l-to" name="to" value="{{ $filters['to'] }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
                        <div class="col-sm-4 col-lg-1">
                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="voided" value="1" id="l-voided" @checked($filters['voided'])><label class="form-check-label small" for="l-voided">{{ __('finance.show_voided') }}</label></div>
                        </div>
                        <div class="col-12 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
                    </div>
                </form>
            @endunless

            <div @class(['card' => ! $print])>
                <div class="table-responsive">
                    <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                        <thead>
                        <tr>
                            <th>{{ __('finance.date') }}</th>
                            <th>{{ __('finance.tx_type') }}</th>
                            <th>{{ __('finance.category') }}</th>
                            <th class="text-end">{{ __('finance.amount') }}</th>
                            <th>{{ __('finance.party') }}</th>
                            <th>{{ __('finance.description') }}</th>
                            @unless ($print)<th></th>@endunless
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($transactions as $tx)
                            <tr @class(['text-secondary text-decoration-line-through' => $tx->isVoided()])>
                                <td class="small text-nowrap">@jdate($tx->date)</td>
                                <td><span class="badge {{ $tx->type->badgeClass() }}">{{ $tx->type->label() }}</span></td>
                                <td>{{ $tx->category->name }}</td>
                                <td class="text-end fw-medium">{{ \App\Support\Money::format($tx->amount) }}</td>
                                <td class="small">{{ $tx->party_name }}</td>
                                <td class="small">{{ $tx->description }}@if ($tx->isVoided())<div class="text-danger">{{ __('finance.voided') }}: {{ $tx->void_reason }}</div>@endif</td>
                                @unless ($print)
                                    <td class="text-end">
                                        @if (! $tx->isVoided() && auth()->user()->can('finance.manage'))
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#txVoid{{ $tx->id }}">{{ __('finance.void') }}</button>
                                        @endif
                                    </td>
                                @endunless
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-4">{{ __('finance.no_transactions') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (! $print && method_exists($transactions, 'links'))<div class="mt-3">{{ $transactions->links() }}</div>@endif
        </div>

        @unless ($print)
            <div class="tab-pane fade" id="categories-pane" role="tabpanel">
                @can('finance.manage')
                    <form method="POST" action="{{ route('finance.ledger.categories.store') }}" class="card card-body mb-3">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-3">
                                <label for="c-type" class="form-label">{{ __('finance.tx_type') }}</label>
                                <select name="type" id="c-type" class="form-select">@foreach (\App\Enums\TransactionType::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="col-sm-6"><label for="c-name" class="form-label">{{ __('finance.type_name') }}</label><input type="text" id="c-name" name="name" maxlength="100" required class="form-control"></div>
                            <div class="col-sm-3"><button type="submit" class="btn btn-primary w-100">{{ __('finance.category_create') }}</button></div>
                        </div>
                    </form>
                @endcan
                <div class="card">
                    <ul class="list-group list-group-flush">
                        @foreach ($categories as $c)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="badge {{ $c->type->badgeClass() }}">{{ $c->type->label() }}</span>
                                <span @class(['flex-grow-1', 'text-secondary' => ! $c->is_active])>{{ $c->name }}@unless ($c->is_active) ({{ __('finance.inactive') }})@endunless</span>
                                @can('finance.manage')
                                    <form method="POST" action="{{ route('finance.ledger.categories.toggle', $c) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $c->is_active ? __('finance.deactivate') : __('finance.activate') }}</button>
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endunless
    </div>

    @if (! $print && auth()->user()->can('finance.manage'))
        <x-modal-form id="txCreate" :title="__('finance.tx_create')" :action="route('finance.ledger.store')" :submit="__('app.save')">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label for="tx-type" class="form-label">{{ __('finance.tx_type') }} <span class="text-danger">*</span></label>
                    <select name="type" id="tx-type" class="form-select" data-filter-target="#tx-cat">
                        @foreach (\App\Enums\TransactionType::options() as $v => $l)<option value="{{ $v }}" @selected(old('type', 'expense') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-7 mb-3">
                    <label for="tx-cat" class="form-label">{{ __('finance.category') }} <span class="text-danger">*</span></label>
                    <select name="finance_category_id" id="tx-cat" class="form-select">
                        @foreach ($categories->where('is_active', true) as $c)
                            <option value="{{ $c->id }}" data-type="{{ $c->type->value }}" @selected((int) old('finance_category_id') === $c->id)>{{ $c->type->label() }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-form.input class="col-md-6 mb-3" name="amount" :label="__('finance.amount').' ('.__('finance.afn').')'" required inputmode="decimal" ltr :use-old="old('_modal') === 'txCreate'" />
                <x-jalali-date class="col-md-6 mb-3" name="date" :label="__('finance.date')" :value="now()" required :use-old="old('_modal') === 'txCreate'" />
                <x-form.input class="col-12 mb-3" name="party_name" :label="__('finance.party')" maxlength="150" :placeholder="__('finance.party_ph')" :use-old="old('_modal') === 'txCreate'" />
                <x-form.input class="col-12 mb-0" name="description" :label="__('finance.description')" maxlength="500" :use-old="old('_modal') === 'txCreate'" />
            </div>
        </x-modal-form>
        @foreach ($transactions as $tx)
            @unless ($tx->isVoided())
                <x-modal-form id="txVoid{{ $tx->id }}" :title="__('finance.void')" :action="route('finance.ledger.void', $tx)" method="PATCH" :submit="__('finance.void')" submit-class="btn-danger">
                    <p class="small">{{ $tx->type->label() }} — {{ $tx->category->name }} — {{ \App\Support\Money::format($tx->amount) }}</p>
                    <x-form.input name="reason" :label="__('finance.void_reason')" required maxlength="500" :use-old="old('_modal') === 'txVoid'.$tx->id" />
                </x-modal-form>
            @endunless
        @endforeach
    @endif
@endsection
