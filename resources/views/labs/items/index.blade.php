@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('labs.items_title')])

@php
    $t = 'labs.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $q = fn ($v) => \App\Support\Quantity::format($v);
    $labName = $labs->firstWhere('id', (int) ($filters['lab'] ?? 0))?->name;
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __($t.'count_sheet') }}@if ($labName) — {{ $labName }}@endif</h1>
        <p class="text-center small mb-3">{{ __($t.'count_sheet_hint') }} · @jdate(now())</p>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __($t.'items_title') }}</h1>
            <div class="d-flex flex-wrap gap-2">
                @if ($canManage)
                    <a href="{{ route('labs.items.create', ['lab' => $filters['lab'] ?? null]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'add_item') }}</a>
                    <a href="{{ route('import.index', ['type' => 'lab_items']) }}" class="btn btn-outline-success"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> {{ __($t.'import_items') }}</a>
                @endif
                <a href="{{ route('labs.items.index', array_merge(request()->query(), ['print' => 1])) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __($t.'print_count_sheet') }}</a>
                <a href="{{ route('labs.items.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ $canManage ? __($t.'items_intro_manager') : __($t.'items_intro') }}</div>

        @if ($labs->isEmpty())
            <div class="alert alert-warning">{{ __($t.'no_labs') }} @if ($canManage)<a href="{{ route('labs.setup.index') }}">{{ __('menu.labs_setup') }}</a>@endif</div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach (['low' => 'text-bg-warning', 'expired' => 'text-bg-danger', 'expiring' => 'text-bg-info', 'damaged' => 'text-bg-secondary'] as $key => $badge)
                <a href="{{ route('labs.items.index', ['alert' => $key, 'lab' => $filters['lab'] ?? null]) }}" class="badge {{ $alerts[$key] ? $badge : 'text-bg-light border' }} text-decoration-none fs-6 fw-normal">{{ __($t.'alert_n.'.$key, ['count' => $n($alerts[$key])]) }}</a>
            @endforeach
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label for="li-lab" class="form-label">{{ __($t.'lab') }}</label>
                    <select name="lab" id="li-lab" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) ($filters['lab'] ?? 0) === $l->id)>{{ $l->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="li-kind" class="form-label">{{ __($t.'kind') }}</label>
                    <select name="kind" id="li-kind" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (\App\Enums\LabItemKind::options() as $v => $l)<option value="{{ $v }}" @selected(($filters['kind'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="li-alert" class="form-label">{{ __($t.'alert') }}</label>
                    <select name="alert" id="li-alert" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (__($t.'alerts') as $v => $l)<option value="{{ $v }}" @selected(($filters['alert'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="li-q" class="form-label">{{ __('app.search') }}</label>
                    <input type="search" name="q" id="li-q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __($t.'ph.search') }}">
                </div>
                <div class="col-md-2">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="inactive" value="1" id="li-inactive" @checked($filters['inactive'] ?? false)>
                        <label class="form-check-label small" for="li-inactive">{{ __($t.'show_inactive') }}</label>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button>
                </div>
            </div>
        </form>
    @endif

    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                <thead><tr>
                    <th>{{ __($t.'code') }}</th><th>{{ __($t.'name') }}</th>
                    @unless ($labName)<th>{{ __($t.'lab') }}</th>@endunless
                    <th>{{ __($t.'kind') }}</th><th>{{ __($t.'location') }}</th>
                    <th class="text-center">{{ __($t.'quantity') }}</th>
                    @if ($print)<th class="text-center" style="width: 18%">{{ __($t.'counted') }}</th><th style="width: 18%">{{ __($t.'remark') }}</th>
                    @else<th>{{ __($t.'status') }}</th><th></th>@endif
                </tr></thead>
                <tbody>
                @forelse ($items as $i)
                    <tr @class(['text-secondary' => ! $i->is_active])>
                        <td class="small">@fa($i->code ?: '—')</td>
                        <td>
                            @if ($print){{ $i->name }}@else<a href="{{ route('labs.items.show', $i) }}" class="fw-medium text-decoration-none">{{ $i->name }}</a>@endif
                            @if ($i->hasHazard()) <span class="badge {{ $i->hazard->badgeClass() }}">{{ $i->hazard->label() }}</span>@endif
                            @if ($i->specification)<div class="small text-secondary">{{ $i->specification }}</div>@endif
                        </td>
                        @unless ($labName)<td class="small">{{ $i->lab->name }}</td>@endunless
                        <td class="small">{{ $i->kind->label() }}</td>
                        <td class="small">@fa($i->location ?: '—')</td>
                        <td class="text-center text-nowrap">{{ $q($i->quantity) }} <span class="small text-secondary">{{ $i->unit->label() }}</span>
                            @if ((float) $i->damaged_quantity > 0)<div class="small text-warning-emphasis">{{ __($t.'damaged_n', ['count' => $q($i->damaged_quantity)]) }}</div>@endif</td>
                        @if ($print)
                            <td></td><td></td>
                        @else
                            <td class="small">
                                @if ($i->isLow())<span class="badge text-bg-warning">{{ __($t.'alerts.low') }}</span>@endif
                                @if ($i->isExpired())<span class="badge text-bg-danger">{{ __($t.'alerts.expired') }}</span>
                                @elseif ($i->isExpiring())<span class="badge text-bg-info">{{ __($t.'expires') }} @jdate($i->expires_on)</span>@endif
                                @unless ($i->is_active)<span class="badge text-bg-secondary">{{ __('app.inactive') }}</span>@endunless
                            </td>
                            <td class="text-end"><a href="{{ route('labs.items.show', $i) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">{{ __($t.'no_items') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($print)
        <div class="d-flex justify-content-around mt-5 small">
            <div class="text-center">..............................<br>{{ __($t.'sign_manager') }}</div>
            <div class="text-center">..............................<br>{{ __('app.signature_principal') }}</div>
        </div>
    @else
        <div class="mt-3">{{ $items->links() }}</div>
    @endif
@endsection
