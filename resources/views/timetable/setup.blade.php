@extends('layouts.app', ['title' => __('timetable.setup_title')])

@php($t = 'timetable.')

@section('content')
    <h1 class="page-title">{{ __($t.'setup_title') }}</h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'setup_intro') }}</div>

    <div class="row g-3">
        {{-- ساعت‌های درسی --}}
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="fw-medium flex-grow-1"><i class="fa-solid fa-clock" aria-hidden="true"></i> {{ __($t.'periods') }}</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#periodNew"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'add_period') }}</button>
                </div>
                <div class="card-body">
                    @foreach (\App\Enums\Shift::cases() as $shift)
                        @php($activeP = isset($periods[$shift->value]) ? $periods[$shift->value]->where('is_active', true) : collect())
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="h6 fw-bold mb-0 flex-grow-1">{{ $shift->label() }}
                                @if ($activeP->isNotEmpty())<span class="small fw-normal text-secondary">— {{ __($t.'plan_summary', ['start' => \App\Support\Digits::toPersian(substr($activeP->first()->start_time, 0, 5)), 'end' => \App\Support\Digits::toPersian(substr($activeP->last()->end_time, 0, 5))]) }}</span>@endif
                            </h2>
                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#plan-{{ $shift->value }}"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> {{ __($t.'plan_button') }}</button>
                        </div>
                        @if (isset($periods[$shift->value]))
                            <div class="table-responsive"><table class="table table-sm align-middle mb-3">
                                <thead><tr><th>#</th><th>{{ __($t.'period_name') }}</th><th>{{ __($t.'time') }}</th><th></th><th></th></tr></thead>
                                <tbody>
                                @foreach ($periods[$shift->value] as $p)
                                    <tr @class(['text-secondary' => ! $p->is_active])>
                                        <td>@fa($p->sort_order)</td>
                                        <td>{{ $p->name }}@if ($p->is_break) <span class="badge text-bg-light border">{{ __($t.'break') }}</span>@endif
                                            @unless ($p->is_active) <span class="badge text-bg-secondary">{{ __('app.inactive') }}</span>@endunless</td>
                                        <td class="text-nowrap">@fa($p->timeRange())</td>
                                        <td class="small text-secondary">@if ($p->entries_count){{ __($t.'used_in', ['count' => \App\Support\Digits::toPersian((string) $p->entries_count)]) }}@endif</td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#period{{ $p->id }}">{{ __('app.edit') }}</button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table></div>
                        @else
                            <p class="small text-secondary">{{ __($t.'no_periods_shift') }}</p>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- نوبت صنف‌ها --}}
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header fw-medium"><i class="fa-solid fa-sun" aria-hidden="true"></i> {{ __($t.'grade_shifts') }}</div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __($t.'grade_shifts_intro') }}</p>
                    @if ($grades->isEmpty())
                        <p class="small text-secondary mb-0">{{ __($t.'no_sections_year') }}</p>
                    @else
                        <form method="POST" action="{{ route('timetable.setup.shifts') }}" class="mb-3">
                            @csrf
                            <input type="hidden" name="preset" value="common">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> {{ __($t.'grade_shifts_preset') }}</button>
                        </form>
                        <form method="POST" action="{{ route('timetable.setup.shifts') }}">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead><tr><th>{{ __('academics.grade') }}</th><th>{{ __($t.'grade_sections') }}</th><th style="min-width: 9rem">{{ __('academics.shift') }}</th></tr></thead>
                                    <tbody>
                                    @foreach ($grades as $g)
                                        @php($current = $g['sections']->countBy(fn ($s) => $s->shift->value)->sortDesc()->keys()->first())
                                        <tr>
                                            <td class="fw-medium">{{ $g['grade']->name }}</td>
                                            <td class="small text-secondary">{{ $g['sections']->map(fn ($s) => $s->name.' ('.$s->shift->label().')')->join('، ') }}</td>
                                            <td>
                                                <select name="shifts[{{ $g['grade']->id }}]" class="form-select form-select-sm" aria-label="{{ __('academics.shift') }} — {{ $g['grade']->name }}">
                                                    @foreach (\App\Enums\Shift::options() as $v => $l)<option value="{{ $v }}" @selected($current === $v)>{{ $l }}</option>@endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">{{ __($t.'grade_shifts_save') }}</button>
                        </form>
                    @endif
                </div>
            </div>

        {{-- اتاق‌ها --}}
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="fw-medium flex-grow-1"><i class="fa-solid fa-door-open" aria-hidden="true"></i> {{ __($t.'rooms') }}</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#roomNew"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'add_room') }}</button>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($rooms as $r)
                        <li @class(['list-group-item d-flex align-items-center gap-2', 'text-secondary' => ! $r->is_active])>
                            <span class="flex-grow-1">{{ $r->name }} <span class="small text-secondary">— {{ $r->type->label() }}@if ($r->capacity) · {{ __($t.'capacity_n', ['count' => \App\Support\Digits::toPersian((string) $r->capacity)]) }}@endif</span>
                                @unless ($r->is_active) <span class="badge text-bg-secondary">{{ __('app.inactive') }}</span>@endunless</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#room{{ $r->id }}">{{ __('app.edit') }}</button>
                        </li>
                    @empty
                        <li class="list-group-item small text-secondary">{{ __($t.'no_rooms_hint') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- پنجره‌ها --}}
    {{-- تنظیم وقت هر نوبت --}}
    @foreach (\App\Enums\Shift::cases() as $shift)
        @php($pid = 'plan-'.$shift->value)
        @php($open = old('_modal') === $pid)
        @php($plan = $plans[$shift->value])
        <x-modal-form :id="$pid" :title="__($t.'plan_title', ['shift' => $shift->label()])" :action="route('timetable.setup.periods.generate')" :submit="__('app.save')">
            <input type="hidden" name="shift" value="{{ $shift->value }}">
            <p class="small text-secondary">{{ __($t.'plan_help') }}</p>
            <div class="row">
                <x-form.input class="col-6 mb-3" :id="$pid.'-start'" name="start_time" type="time" ltr :label="__($t.'plan_start')" :value="$plan['start_time']" required :use-old="$open" />
                <x-form.input class="col-6 mb-3" :id="$pid.'-min'" name="minutes" type="number" min="20" max="90" :label="__($t.'plan_minutes')" :value="$plan['minutes']" required :use-old="$open" placeholder="35" />
                <x-form.input class="col-12 mb-3" :id="$pid.'-n'" name="lessons" type="number" min="1" max="10" :label="__($t.'plan_lessons')" :value="$plan['lessons']" required :use-old="$open" placeholder="6" />
                <x-form.input class="col-6 mb-3" :id="$pid.'-ba'" name="break_after" type="number" min="0" max="10" :label="__($t.'plan_break_after')" :value="$plan['break_after']" required :use-old="$open" placeholder="3" :help="__($t.'plan_break_help')" />
                <x-form.input class="col-6 mb-3" :id="$pid.'-bm'" name="break_minutes" type="number" min="0" max="60" :label="__($t.'plan_break_minutes')" :value="$plan['break_minutes']" required :use-old="$open" placeholder="15" />
            </div>
        </x-modal-form>
    @endforeach

    @php($shifts = \App\Enums\Shift::options())
    @php($types = \App\Enums\RoomType::options())
    @foreach (array_merge([null], $periods->flatten()->all()) as $p)
        @php($id = $p ? 'period'.$p->id : 'periodNew')
        @php($open = old('_modal') === $id)
        <x-modal-form :id="$id" :title="$p ? __('app.edit').' — '.$p->name : __($t.'add_period')" :action="$p ? route('timetable.setup.periods.update', $p) : route('timetable.setup.periods.store')" :method="$p ? 'PUT' : 'POST'" :submit="__('app.save')">
            <div class="row">
                <x-form.input class="col-8 mb-3" :id="$id.'-name'" name="name" :label="__($t.'period_name')" :value="$p?->name" required maxlength="50" :use-old="$open" :placeholder="__($t.'period_name_ph')" />
                <x-form.input class="col-4 mb-3" :id="$id.'-sort'" name="sort_order" type="number" min="0" max="50" :label="__($t.'sort_order')" :value="$p?->sort_order ?? 1" required :use-old="$open" />
                <div class="col-12 mb-3">
                    <label class="form-label" for="{{ $id }}-shift">{{ __('academics.shift') }}</label>
                    <select name="shift" id="{{ $id }}-shift" class="form-select">
                        @foreach ($shifts as $v => $l)<option value="{{ $v }}" @selected(($open ? old('shift') : $p?->shift?->value) === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <x-form.input class="col-6 mb-3" :id="$id.'-start'" name="start_time" type="time" ltr :label="__($t.'start_time')" :value="$p ? substr($p->start_time, 0, 5) : ''" required :use-old="$open" />
                <x-form.input class="col-6 mb-3" :id="$id.'-end'" name="end_time" type="time" ltr :label="__($t.'end_time')" :value="$p ? substr($p->end_time, 0, 5) : ''" required :use-old="$open" />
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_break" value="1" id="{{ $id }}-break" @checked($open ? old('is_break') : $p?->is_break)>
                <label class="form-check-label" for="{{ $id }}-break">{{ __($t.'is_break') }}</label>
            </div>
            @if ($p)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $id }}-active" @checked($open ? old('is_active') : $p->is_active)>
                    <label class="form-check-label" for="{{ $id }}-active">{{ __('app.active') }}</label>
                </div>
                <div class="form-text">{{ __($t.'inactive_hint') }}</div>
            @endif
        </x-modal-form>
    @endforeach

    @foreach (array_merge([null], $rooms->all()) as $r)
        @php($id = $r ? 'room'.$r->id : 'roomNew')
        @php($open = old('_modal') === $id)
        <x-modal-form :id="$id" :title="$r ? __('app.edit').' — '.$r->name : __($t.'add_room')" :action="$r ? route('timetable.setup.rooms.update', $r) : route('timetable.setup.rooms.store')" :method="$r ? 'PUT' : 'POST'" :submit="__('app.save')">
            <x-form.input :id="$id.'-name'" name="name" :label="__($t.'room_name')" :value="$r?->name" required maxlength="100" :use-old="$open" :placeholder="__($t.'room_name_ph')" />
            <div class="row">
                <div class="col-7 mb-3">
                    <label class="form-label" for="{{ $id }}-type">{{ __($t.'room_type') }}</label>
                    <select name="type" id="{{ $id }}-type" class="form-select">
                        @foreach ($types as $v => $l)<option value="{{ $v }}" @selected(($open ? old('type') : $r?->type?->value ?? 'classroom') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <x-form.input class="col-5 mb-3" :id="$id.'-cap'" name="capacity" type="number" min="1" max="1000" :label="__($t.'capacity')" :value="$r?->capacity" :use-old="$open" :placeholder="__($t.'capacity_ph')" />
            </div>
            <x-form.input :id="$id.'-notes'" name="notes" :label="__($t.'room_notes')" :value="$r?->notes" maxlength="300" :use-old="$open" :placeholder="__($t.'room_notes_ph')" />
            @if ($r)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $id }}-active" @checked($open ? old('is_active') : $r->is_active)>
                    <label class="form-check-label" for="{{ $id }}-active">{{ __('app.active') }}</label>
                </div>
            @endif
        </x-modal-form>
    @endforeach
@endsection
