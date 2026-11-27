@extends('layouts.app', ['title' => __('courses.sheet_title').' — '.$group->name])

@php
    $statuses = \App\Enums\AttendanceStatus::cases();
    $dayKey = $date->format('Y-m-d');
@endphp

@section('content')
    <div class="card profile-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="section-badge section-badge-lg"><i class="fa-solid fa-clipboard-user" aria-hidden="true"></i></span>
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ __('courses.sheet_title') }} — {{ $group->course->name }}</h1>
                <div class="text-secondary small">
                    @jdate($date, 'full') · {{ $group->name }} · {{ $group->shift->label() }}
                    · {{ __('courses.teacher') }}: {{ $group->teacher?->full_name ?? '—' }}
                </div>
                @if ($session)
                    <div class="small mt-1">
                        <span class="badge text-bg-success"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('courses.taken') }}</span>
                        <span class="text-secondary">{{ __('attendance.taken_by', ['name' => $session->takenBy?->name ?? '—', 'time' => \App\Support\Clock::withPeriod($session->created_at)]) }}</span>
                        @if ($session->updatedBy)<span class="text-secondary">· {{ __('attendance.corrected_by', ['name' => $session->updatedBy->name]) }}</span>@endif
                    </div>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('view', $group->course)
                    <a href="{{ route('courses.list.show', $group->course) }}#participants" class="btn btn-outline-secondary">{{ __('courses.tab_participants') }}</a>
                @endcan
                <a href="{{ route('courses.attendance.index', ['date' => $dayKey]) }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
    </div>

    @include('attendance.partials.date-bar', ['route' => 'courses.attendance.show', 'params' => ['group' => $group->id]])

    @if (! $canRecord)
        <div class="alert alert-info"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ __('attendance.read_only') }} — {{ $blocked }}</div>
    @elseif ($session)
        <div class="alert alert-warning small"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('attendance.sheet_correct_hint') }}</div>
    @endif

    @error('status')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @if ($participants->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('courses.no_active_participants') }}</div>
    @elseif (! $canRecord && ! $session)
        <div class="card card-body text-center text-secondary py-5">{{ __('attendance.not_taken') }}</div>
    @else
        <form method="POST" action="{{ route('courses.attendance.store', $group) }}" data-att-form>
            @csrf
            <input type="hidden" name="date" value="{{ $dayKey }}">

            @if ($canRecord)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-success" data-att-all="present"><i class="fa-solid fa-check-double" aria-hidden="true"></i> {{ __('attendance.all_present') }}</button>
                </div>
            @endif

            <div class="card att-sheet">
                <ul class="list-group list-group-flush">
                    @foreach ($participants as $id => $p)
                        @php
                            $record = $records->get($id);
                            $current = old('status.'.$id, $record?->status->value ?? 'present');
                        @endphp
                        <li class="list-group-item att-row">
                            <div class="att-who">
                                <span class="att-roll">@fa($loop->iteration)</span>
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate">{{ $p->display_name }}
                                        @unless ($p->isSchoolStudent())<span class="badge text-bg-light border">{{ __('courses.external') }}</span>@endunless
                                        @unless ($p->status === \App\Enums\ParticipantStatus::Active)<span class="badge text-bg-light border">{{ $p->status->label() }}</span>@endunless
                                    </div>
                                    <div class="small text-secondary text-truncate">{{ __('students.father_name') }}: {{ $p->display_father_name ?? '—' }}</div>
                                </div>
                            </div>
                            @if ($canRecord)
                                <div class="att-choices" role="radiogroup" aria-label="{{ $p->display_name }}">
                                    @foreach ($statuses as $status)
                                        <input type="radio" class="btn-check" name="status[{{ $id }}]" id="st-{{ $id }}-{{ $status->value }}" value="{{ $status->value }}" autocomplete="off" @checked($current === $status->value)>
                                        <label class="btn btn-outline-{{ $status->buttonClass() }}" for="st-{{ $id }}-{{ $status->value }}" title="{{ $status->label() }}">
                                            <span class="d-none d-md-inline">{{ $status->label() }}</span><span class="d-md-none">{{ $status->code() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <input type="text" name="note[{{ $id }}]" value="{{ old('note.'.$id, $record?->note) }}" maxlength="255"
                                       class="form-control form-control-sm att-note" placeholder="{{ __('attendance.note_ph') }}" aria-label="{{ __('attendance.note') }} — {{ $p->display_name }}">
                            @else
                                <div>
                                    @if ($record)
                                        <span class="badge {{ $record->status->badgeClass() }}">{{ $record->status->label() }}</span>
                                        @if ($record->note)<span class="small text-secondary ms-1">{{ $record->note }}</span>@endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($canRecord)
                <div class="att-savebar">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach ($statuses as $status)
                            <span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}: <span data-att-count="{{ $status->value }}">—</span></span>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('attendance.save_sheet') }}</button>
                </div>
            @endif
        </form>
    @endif
@endsection
