@extends('layouts.app', ['title' => $item->name])

@php
    $t = 'labs.';
    $q = fn ($v) => \App\Support\Quantity::format($v);
    $unit = $item->unit->label();
    $sign = fn ($e) => match ($e->type) {
        \App\Enums\LabEventType::Received, \App\Enums\LabEventType::Repaired => '+',
        \App\Enums\LabEventType::Adjusted, \App\Enums\LabEventType::WrittenOff => '',
        default => '−',
    };
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $item->name }}
            @if ($item->hasHazard()) <span class="badge {{ $item->hazard->badgeClass() }} fs-6">{{ $item->hazard->label() }}</span>@endif
            @unless ($item->is_active) <span class="badge text-bg-secondary fs-6">{{ __('app.inactive') }}</span>@endunless
        </h1>
        <div class="d-flex flex-wrap gap-2">
            @if ($canManage)<a href="{{ route('labs.items.edit', $item) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>@endif
            <a href="{{ route('labs.items.index', ['lab' => $item->lab_id]) }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex gap-3 mb-3">
                        <div class="flex-fill text-center border rounded py-2">
                            <div class="small text-secondary">{{ __($t.'usable') }}</div>
                            <div class="fs-3 fw-bold @if ($item->isLow()) text-warning-emphasis @endif">{{ $q($item->quantity) }}</div>
                            <div class="small">{{ $unit }}</div>
                        </div>
                        <div class="flex-fill text-center border rounded py-2">
                            <div class="small text-secondary">{{ __($t.'damaged') }}</div>
                            <div class="fs-3 fw-bold">{{ $q($item->damaged_quantity) }}</div>
                            <div class="small">{{ $unit }}</div>
                        </div>
                    </div>
                    @if ($item->isLow())<div class="alert alert-warning small py-2">{{ __($t.'low_warning', ['min' => $q($item->min_quantity), 'unit' => $unit]) }}</div>@endif
                    @if ($item->isExpired())<div class="alert alert-danger small py-2">{{ __($t.'expired_warning') }}</div>
                    @elseif ($item->isExpiring())<div class="alert alert-info small py-2">{{ __($t.'expiring_warning', ['date' => \App\Support\JalaliDate::format($item->expires_on)]) }}</div>@endif
                    <dl class="row mb-0 small">
                        @foreach ([
                            'lab' => $item->lab->name, 'code' => $item->code, 'kind' => $item->kind->label(), 'location' => $item->location,
                            'min_quantity' => $item->min_quantity !== null ? $q($item->min_quantity).' '.$unit : null,
                            'expires_on' => $item->expires_on ? \App\Support\JalaliDate::format($item->expires_on) : null,
                            'unit_price' => $item->unit_price !== null ? \App\Support\Money::format($item->unit_price) : null,
                            'source' => $item->source->label(), 'specification' => $item->specification, 'notes' => $item->notes,
                        ] as $key => $value)
                            @if ($value !== null && $value !== '')
                                <dt class="col-5 text-secondary fw-normal">{{ __($t.$key) }}</dt><dd class="col-7">{{ $value }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="col-lg-7">
                <form method="POST" action="{{ route('labs.items.move', $item) }}" class="card h-100" novalidate>
                    @csrf
                    <div class="card-header fw-medium"><i class="fa-solid fa-right-left" aria-hidden="true"></i> {{ __($t.'move_title') }}</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="mv-type">{{ __($t.'event_type') }}</label>
                                <select name="type" id="mv-type" class="form-select">
                                    @foreach (\App\Enums\LabEventType::cases() as $type)
                                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }} — {{ __($t.'event_hint.'.$type->value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-form.input class="col-6 col-md-3" name="amount" :label="__($t.'amount').' ('.$unit.')'" inputmode="decimal" required :placeholder="__($t.'ph.amount')" />
                            <x-jalali-date class="col-6 col-md-3" name="event_date" :label="__($t.'event_date')" :placeholder="__('app.today')" />
                            <x-form.input class="col-12" name="note" :label="__($t.'note')" maxlength="300" :placeholder="__($t.'ph.note')" :help="__($t.'help.note')" />
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __($t.'record') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header fw-medium"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> {{ __($t.'ledger') }}</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __($t.'event_date') }}</th><th>{{ __($t.'event_type') }}</th><th class="text-center">{{ __($t.'amount') }}</th><th class="text-center">{{ __($t.'balance') }}</th><th>{{ __($t.'note') }}</th><th>{{ __('app.created_by') }}</th></tr></thead>
                <tbody>
                @forelse ($events as $e)
                    <tr>
                        <td class="small">@jdate($e->event_date)</td>
                        <td><span class="badge {{ $e->type->badgeClass() }}">{{ $e->type->label() }}</span></td>
                        <td class="text-center text-nowrap" dir="ltr">{{ $e->type === \App\Enums\LabEventType::Adjusted && \App\Support\Quantity::toHundredths($e->quantity) > 0 ? '+' : $sign($e) }}{{ $q($e->quantity) }}</td>
                        <td class="text-center">{{ $q($e->balance_after) }}</td>
                        <td class="small">{{ $e->note }}
                            @if ($e->activity)<div><a href="{{ route('labs.activities.show', $e->activity) }}">{{ $e->activity->section->gradeLevel->name }} {{ $e->activity->section->name }}</a></div>@endif</td>
                        <td class="small text-secondary">{{ $e->user?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">{{ __($t.'no_events') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $events->links() }}</div>
@endsection
