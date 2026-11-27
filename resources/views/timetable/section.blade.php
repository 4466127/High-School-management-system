@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('timetable.title')])

@section('content')
    @if ($print)
        @if ($section)
            <h1 class="h5 fw-bold text-center mb-3">{{ __('timetable.title') }} — {{ $section->label }} — {{ __('academics.year') }} @fa($year->name)</h1>
        @endif
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('timetable.title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
            @if ($section)
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('timetable.sections.index', ['section' => $section->id, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                    @if ($canManage)
                        <a href="{{ route('timetable.sections.index', ['all' => 1, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ __('timetable.print_all') }}</a>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @elseif ($sections->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('attendance.no_my_sections') }}</div>
    @else
        @unless ($print)
            @if ($canManage)
                <div class="alert alert-light border small mb-3">
                    <div class="fw-medium mb-1"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('timetable.steps_title') }}</div>
                    <ol class="mb-1 ps-3">@foreach (__('timetable.steps') as $step)<li>{{ $step }}</li>@endforeach</ol>
                    <div>{{ __('timetable.intro') }}</div>
                </div>
            @endif
            <form method="GET" class="card card-body mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-8 col-lg-5">
                        <label for="tt-section" class="form-label">{{ __('academics.section') }}</label>
                        <select name="section" id="tt-section" class="form-select" data-submit-on-change>
                            @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($section && $section->id === $s->id)>{{ $s->label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-4 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.view') }}</button></div>
                </div>
            </form>
        @endunless

        @if ($periods->isEmpty())
            <div class="alert alert-warning">
                {{ __('timetable.no_periods', ['shift' => $section->shift->label()]) }}
                @if ($canManage && ! $print)<a href="{{ route('timetable.setup.index') }}" class="alert-link">{{ __('timetable.go_setup') }}</a>@endif
            </div>
        @else
            <div @class(['card' => ! $print, 'print-landscape' => $print])>
                @include('timetable._grid', ['mode' => 'section', 'editable' => $canManage && ! $print])
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-7">
                    <div class="small">
                        <span class="fw-medium">{{ __('timetable.hours_per_subject') }}:</span>
                        @forelse ($hours as $h)
                            <span class="badge text-bg-light border fw-normal">{{ $h['subject']->name }} @fa($h['hours'])</span>
                        @empty
                            <span class="text-secondary">{{ __('timetable.empty_section') }}</span>
                        @endforelse
                        @php($missing = $section->gradeLevel->subjects->whereNotIn('id', $hours->pluck('subject.id')))
                        @if ($hours->isNotEmpty() && $missing->isNotEmpty() && ! $print)
                            <div class="text-warning-emphasis mt-1">{{ __('timetable.subjects_missing', ['subjects' => $missing->pluck('name')->implode('، ')]) }}</div>
                        @endif
                    </div>
                </div>
                @if ($canManage && ! $print && $siblings->isNotEmpty())
                    <div class="col-lg-5">
                        <form method="POST" action="{{ route('timetable.sections.copy', $section) }}" class="d-flex gap-2 align-items-end" data-confirm="{{ __('timetable.copy_confirm') }}">
                            @csrf
                            <div class="flex-grow-1">
                                <label for="tt-copy" class="form-label small mb-1">{{ __('timetable.copy_from') }}</label>
                                <select name="from" id="tt-copy" class="form-select form-select-sm">
                                    @foreach ($siblings as $s)<option value="{{ $s->id }}">{{ $s->label }}</option>@endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">{{ __('timetable.copy') }}</button>
                        </form>
                        <div class="form-text">{{ __('timetable.copy_hint') }}</div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- پنجره‌ی یک خانه (school.js آن را با روز و ساعت خانه‌ی کلیک‌شده پر می‌کند) --}}
    @if ($section && $canManage && ! $print && $periods->isNotEmpty())
        @php($open = old('_modal') === 'ttCell')
        <x-modal-form id="ttCell" :title="__('timetable.cell_title')" :action="route('timetable.sections.cell', $section)" :submit="__('app.save')">
            <input type="hidden" name="day" value="{{ $open ? old('day') : '' }}" data-tt-field="day">
            <input type="hidden" name="period_id" value="{{ $open ? old('period_id') : '' }}" data-tt-field="period">
            <p class="fw-bold mb-2" data-tt-label></p>
            <div class="mb-3">
                <label for="tt-subject" class="form-label">{{ __('academics.subject') }}</label>
                <select name="subject_id" id="tt-subject" class="form-select @if ($open) @error('subject_id') is-invalid @enderror @endif" data-tt-field="subject" data-tt-defaults='@json($defaults)'>
                    <option value="">{{ __('timetable.no_lesson') }}</option>
                    @foreach ($section->gradeLevel->subjects as $subject)<option value="{{ $subject->id }}" @selected($open && (int) old('subject_id') === $subject->id)>{{ $subject->name }}</option>@endforeach
                </select>
                <div class="form-text">{{ __('timetable.subject_hint') }}</div>
                @if ($open) @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="mb-3">
                <label for="tt-staff" class="form-label">{{ __('timetable.teacher') }}</label>
                <select name="staff_id" id="tt-staff" class="form-select @if ($open) @error('staff_id') is-invalid @enderror @endif" data-tt-field="staff">
                    <option value="">{{ __('timetable.no_teacher') }}</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($open && (int) old('staff_id') === $t->id)>{{ $t->full_name }}</option>@endforeach
                </select>
                <div class="form-text">{{ __('timetable.teacher_hint') }}</div>
                @if ($open) @error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="mb-3">
                <label for="tt-room" class="form-label">{{ __('timetable.room') }}</label>
                <select name="room_id" id="tt-room" class="form-select @if ($open) @error('room_id') is-invalid @enderror @endif" data-tt-field="room">
                    <option value="">{{ __('timetable.no_room') }}</option>
                    @foreach ($rooms as $r)<option value="{{ $r->id }}" @selected($open && (int) old('room_id') === $r->id)>{{ $r->name }}</option>@endforeach
                </select>
                @if ($open) @error('room_id')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <x-form.input id="tt-note" name="note" :label="__('timetable.note')" maxlength="100" :use-old="$open" :placeholder="__('timetable.note_ph')" data-tt-field="note" />
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="clear" value="1" id="tt-clear">
                <label class="form-check-label text-danger" for="tt-clear">{{ __('timetable.clear_cell') }}</label>
            </div>
        </x-modal-form>
    @endif
@endsection
