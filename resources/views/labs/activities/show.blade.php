@extends($print ? 'layouts.print' : 'layouts.app', ['title' => $activity->title])

@php
    $t = 'labs.';
    $q = fn ($v) => \App\Support\Quantity::format($v);
    $editable = $canUpdate && $activity->status !== \App\Enums\ActivityStatus::Cancelled;
    $info = [
        'date' => \App\Support\JalaliDate::format($activity->activity_date, 'full'),
        'period' => $activity->period ? $activity->period->name.' ('.$activity->period->timeRange().')' : null,
        'lab' => $activity->lab->name,
        'subject_section' => $activity->subject->name.' — '.$activity->section->gradeLevel->name.' '.$activity->section->name,
        'teacher' => $activity->teacher?->full_name,
        'max_score' => $activity->max_score ? \App\Support\Digits::toPersian((string) $activity->max_score) : null,
    ];
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __($t.'worksheet') }}: {{ $activity->title }}</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ $activity->title }} <span class="badge {{ $activity->status->badgeClass() }} fs-6">{{ $activity->status->label() }}</span></h1>
            <div class="d-flex flex-wrap gap-2">
                @if ($canUpdate && $activity->status === \App\Enums\ActivityStatus::Planned)
                    <a href="{{ route('labs.activities.edit', $activity) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">{{ __($t.'cancel_activity') }}</button>
                @endif
                <a href="{{ route('labs.activities.show', [$activity, 'print' => 1]) }}" class="btn btn-outline-secondary" target="_blank"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __($t.'print_worksheet') }}</a>
                <a href="{{ route('labs.activities.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
        @if ($activity->status === \App\Enums\ActivityStatus::Cancelled)
            <div class="alert alert-secondary">{{ __($t.'cancelled_because', ['reason' => $activity->cancel_reason]) }}</div>
        @endif
    @endif

    <div class="row g-3 mb-3">
        <div @class(['col-lg-5' => ! $print, 'col-12' => $print])>
            <div @class(['card h-100' => ! $print])>
                <div @class(['card-body' => ! $print])>
                    <dl class="row mb-0 small">
                        @foreach ($info as $key => $value)
                            @if ($value)<dt class="col-4 text-secondary fw-normal">{{ __($t.$key) }}</dt><dd class="col-8">{{ $value }}</dd>@endif
                        @endforeach
                    </dl>
                    @foreach (['objectives' => $activity->objectives, 'materials_note' => $activity->materials_note, 'result_note' => $activity->result_note] as $key => $text)
                        @if ($text)
                            <div class="small fw-medium mt-2">{{ __($t.$key) }}</div>
                            <div class="small letter-body">{{ $text }}</div>
                        @endif
                    @endforeach
                    @if ($activity->lab->safety_notes)
                        <div class="alert alert-warning small mt-3 mb-0 py-2">
                            <div class="fw-medium"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ __($t.'safety_notes') }}</div>
                            <div class="letter-body">{{ $activity->lab->safety_notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @unless ($print)
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header fw-medium"><i class="fa-solid fa-vial" aria-hidden="true"></i> {{ __($t.'materials_used') }}</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($activity->materials as $m)
                            <li class="list-group-item d-flex gap-2 small">
                                <a href="{{ route('labs.items.show', $m->item) }}" class="flex-grow-1">{{ $m->item->name }}</a>
                                <span>{{ $q($m->quantity) }} {{ $m->item->unit->label() }}</span>
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">{{ __($t.'no_materials') }}</li>
                        @endforelse
                    </ul>
                    @if ($editable)
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('labs.activities.material', $activity) }}" class="row g-2 align-items-end" novalidate>
                                @csrf
                                <div class="col-md-7">
                                    <label class="form-label small" for="mat-item">{{ __($t.'item') }}</label>
                                    <select name="item_id" id="mat-item" class="form-select form-select-sm @error('item_id') is-invalid @enderror">
                                        <option value="">{{ __($t.'choose_item') }}</option>
                                        @foreach ($items as $i)<option value="{{ $i->id }}" @selected((int) old('item_id') === $i->id)>{{ $i->name }} ({{ $q($i->quantity) }} {{ $i->unit->label() }})</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small" for="mat-amount">{{ __($t.'amount') }}</label>
                                    <input type="text" name="amount" id="mat-amount" inputmode="decimal" class="form-control form-control-sm @error('amount') is-invalid @enderror" placeholder="{{ __($t.'ph.amount') }}">
                                </div>
                                <div class="col-6 col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('app.add') }}</button></div>
                                @error('amount')<div class="col-12 small text-danger">{{ $message }}</div>@enderror
                            </form>
                            <div class="form-text">{{ __($t.'help.materials_used') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endunless
    </div>

    {{-- حاضری و نتیجه --}}
    @if ($print)
        <table class="table table-bordered table-sm align-middle">
            <thead><tr><th style="width: 3rem">#</th><th>{{ __('students.full_name') }}</th><th>{{ __($t.'father') }}</th><th style="width: 12%">{{ __($t.'present') }}</th>@if ($activity->max_score)<th style="width: 12%">{{ __($t.'score') }} ({{ \App\Support\Digits::toPersian((string) $activity->max_score) }})</th>@endif<th style="width: 25%">{{ __($t.'note') }}</th></tr></thead>
            <tbody>
            @foreach ($enrollments as $e)
                @php($r = $records->get($e->id))
                <tr>
                    <td>@fa($e->roll_no ?? $loop->iteration)</td>
                    <td>{{ $e->student->full_name }}</td>
                    <td>{{ $e->student->father_name }}</td>
                    <td class="text-center">{{ $r ? ($r->present ? '✓' : '✗') : '' }}</td>
                    @if ($activity->max_score)<td class="text-center">{{ $r && $r->score !== null ? \App\Support\Quantity::format($r->score) : '' }}</td>@endif
                    <td class="small">{{ $r?->note }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="d-flex justify-content-around mt-5 small">
            <div class="text-center">..............................<br>{{ __($t.'sign_teacher') }}</div>
            <div class="text-center">..............................<br>{{ __($t.'sign_manager') }}</div>
        </div>
    @else
        <form method="POST" action="{{ route('labs.activities.result', $activity) }}" class="card" novalidate>
            @csrf
            <div class="card-header d-flex align-items-center gap-2">
                <span class="fw-medium flex-grow-1"><i class="fa-solid fa-users" aria-hidden="true"></i> {{ __($t.'attendance_result') }}</span>
                @if ($editable)<button type="button" class="btn btn-sm btn-link p-0" data-check-all="present-boxes">{{ __($t.'toggle_all') }}</button>@endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>#</th><th>{{ __('students.full_name') }}</th><th class="text-center">{{ __($t.'present') }}</th>@if ($activity->max_score)<th style="width: 110px">{{ __($t.'score') }}</th>@endif<th>{{ __($t.'note') }}</th></tr></thead>
                    <tbody>
                    @forelse ($enrollments as $e)
                        @php($r = $records->get($e->id))
                        <tr>
                            <td class="small">@fa($e->roll_no ?? $loop->iteration)</td>
                            <td>{{ $e->student->full_name }} <span class="small text-secondary">{{ __('library.child_of') }} {{ $e->student->father_name }}</span></td>
                            <td class="text-center">
                                <input type="hidden" name="rows[{{ $e->id }}][present]" value="0">
                                <input class="form-check-input" type="checkbox" name="rows[{{ $e->id }}][present]" value="1" data-group="present-boxes" aria-label="{{ __($t.'present') }}" @checked($r ? $r->present : true) @disabled(! $editable)>
                            </td>
                            @if ($activity->max_score)
                                <td><input type="text" inputmode="decimal" name="rows[{{ $e->id }}][score]" class="form-control form-control-sm" value="{{ $r && $r->score !== null ? \App\Support\Quantity::plain($r->score) : '' }}" @disabled(! $editable) aria-label="{{ __($t.'score') }}"></td>
                            @endif
                            <td><input type="text" name="rows[{{ $e->id }}][note]" class="form-control form-control-sm" maxlength="200" value="{{ $r?->note }}" @disabled(! $editable) aria-label="{{ __($t.'note') }}"></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-3">{{ __('library.no_students_section') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($editable)
                <div class="card-body border-top">
                    <x-form.textarea name="result_note" :label="__($t.'result_note')" :value="$activity->result_note" rows="2" maxlength="1000" :placeholder="__($t.'ph.result_note')" />
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ $activity->status === \App\Enums\ActivityStatus::Done ? __($t.'save_correction') : __($t.'mark_done') }}</button>
                </div>
            @endif
        </form>

        @if ($canUpdate && $activity->status === \App\Enums\ActivityStatus::Planned)
            <x-modal-form id="cancelModal" :title="__($t.'cancel_activity')" :action="route('labs.activities.cancel', $activity)" :submit="__($t.'cancel_activity')" submit-class="btn-danger">
                <x-form.input id="cancel-reason" name="reason" :label="__($t.'cancel_reason')" required maxlength="300" :use-old="old('_modal') === 'cancelModal'" :placeholder="__($t.'ph.cancel_reason')" />
            </x-modal-form>
        @endif
    @endif
@endsection
