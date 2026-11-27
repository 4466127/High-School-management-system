@extends('layouts.app', ['title' => __('labs.schedule_title')])

@php($t = 'labs.')

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __($t.'schedule_title') }}</h1>
        @if ($canCreate)<a href="{{ route('labs.activities.create', ['lab' => $lab ?: null]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'plan_activity') }}</a>@endif
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'schedule_intro') }}</div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="sc-lab" class="form-label">{{ __($t.'lab') }}</label>
                <select name="lab" id="sc-lab" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($labs as $l)<option value="{{ $l->id }}" @selected($lab === $l->id)>{{ $l->name }}</option>@endforeach
                </select>
            </div>
            <input type="hidden" name="week" value="{{ $start->toDateString() }}">
            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
            <div class="col-md-6 d-flex gap-2 justify-content-md-end">
                <a class="btn btn-outline-secondary" href="{{ route('labs.schedule.index', ['lab' => $lab ?: null, 'week' => $start->subWeek()->toDateString()]) }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i> {{ __($t.'prev_week') }}</a>
                <a class="btn btn-outline-secondary" href="{{ route('labs.schedule.index', ['lab' => $lab ?: null]) }}">{{ __($t.'this_week') }}</a>
                <a class="btn btn-outline-secondary" href="{{ route('labs.schedule.index', ['lab' => $lab ?: null, 'week' => $start->addWeek()->toDateString()]) }}">{{ __($t.'next_week') }} <i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
            </div>
        </div>
    </form>

    <h2 class="h6 fw-bold mb-2">{{ __($t.'week_of', ['from' => \App\Support\JalaliDate::format($start, 'long'), 'to' => \App\Support\JalaliDate::format($start->addDays(6), 'long')]) }}</h2>
    <div class="row g-2">
        @foreach ($days as $day)
            @php($list = $activities->get($day->toDateString(), collect()))
            @php($isToday = $day->isSameDay(\App\Services\AttendanceService::today()))
            <div class="col-md-6 col-xl-4">
                <div @class(['card h-100', 'border-primary' => $isToday])>
                    <div @class(['card-header small fw-medium', 'text-bg-primary' => $isToday])>@jdate($day, 'full')</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($list as $a)
                            <li class="list-group-item small">
                                <a href="{{ route('labs.activities.show', $a) }}" class="fw-medium text-decoration-none">{{ $a->title }}</a>
                                <span class="badge {{ $a->status->badgeClass() }}">{{ $a->status->label() }}</span>
                                <div class="text-secondary">
                                    @if ($a->period){{ $a->period->name }} · @endif{{ $a->subject->name }} — {{ $a->section->gradeLevel->name }} {{ $a->section->name }}
                                    @unless ($lab) · {{ $a->lab->name }}@endunless
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">{{ __($t.'free_day') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
@endsection
