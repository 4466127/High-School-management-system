@extends('layouts.app', ['title' => __('finance.issue_group')])

@section('content')
    <h1 class="page-title">{{ __('finance.issue_group') }} <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span></h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('finance.issue_group_hint') }}</div>

    @if ($types->isEmpty())
        <div class="card card-body text-center text-secondary py-4">
            {{ __('finance.no_types') }}
            <div class="mt-2"><a href="{{ route('finance.setup.index') }}" class="btn btn-sm btn-primary">{{ __('finance.setup_title') }}</a></div>
        </div>
    @else
        {{-- نوع فیس اول انتخاب می‌شود تا دوره‌ها و نرخ‌ها درست نشان داده شوند --}}
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-8 col-lg-4">
                    <label for="g-type" class="form-label">{{ __('finance.fee_type') }}</label>
                    <select name="fee_type" id="g-type" class="form-select">
                        @foreach ($types as $t)<option value="{{ $t->id }}" @selected($type->id === $t->id)>{{ $t->name }} ({{ $t->period->label() }})</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.view') }}</button></div>
            </div>
        </form>

        <form method="POST" action="{{ route('finance.invoices.store') }}" class="card form-section" data-confirm="{{ __('finance.issue') }}؟" novalidate>
            @csrf
            <input type="hidden" name="fee_type_id" value="{{ $type->id }}">
            <div class="card-header"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> {{ $type->name }}</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="g-period" class="form-label">{{ __('finance.period_label') }} <span class="text-danger">*</span></label>
                        <select name="period_label" id="g-period" class="form-select">
                            @foreach ($periods as $p)<option value="{{ $p }}" @selected(old('period_label') === $p)>{{ $p }}</option>@endforeach
                        </select>
                    </div>
                    <x-jalali-date class="col-md-4 mb-3" name="due_date" :label="__('finance.due_date')" />
                </div>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="form-label mb-0">{{ __('finance.sections_to_bill') }}</span>
                    <button type="button" class="btn btn-sm btn-link p-0" data-check-all="section_ids[]">{{ __('finance.select_all') }}</button>
                </div>
                @error('section_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <div class="row g-2">
                    @foreach ($sections as $s)
                        @php($rate = $rates[$s->grade_level_id] ?? null)
                        <div class="col-sm-6 col-lg-4">
                            <label @class(['bill-section', 'is-disabled' => ! $rate])>
                                <input class="form-check-input" type="checkbox" name="section_ids[]" value="{{ $s->id }}" @checked(in_array($s->id, (array) old('section_ids', []))) @disabled(! $rate)>
                                <span class="flex-grow-1">{{ $s->label }} <span class="small text-secondary">(@fa($s->active_enrollments_count))</span></span>
                                <span class="small {{ $rate ? 'text-success' : 'text-danger' }}">{{ $rate ? \App\Support\Money::format($rate) : __('finance.no_rate') }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('finance.issue') }}</button>
            </div>
        </form>
    @endif
@endsection
