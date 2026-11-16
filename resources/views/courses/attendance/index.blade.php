@extends('layouts.app', ['title' => __('courses.attendance_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('courses.attendance_title') }}</h1>
    </div>
    <p class="text-secondary small">{{ __('courses.attendance_hint') }}</p>

    @include('attendance.partials.date-bar', ['route' => 'courses.attendance.index'])

    @if ($groups->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('courses.no_groups_today') }}</div>
    @else
        <div class="row g-3">
            @foreach ($groups as $group)
                @php($done = in_array($group->id, $taken, true))
                <div class="col-sm-6 col-xl-4">
                    <div @class(['card h-100', 'border-success' => $done])>
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ $group->course->name }}</div>
                                    <div class="small">{{ $group->name }} · {{ $group->shift->label() }}</div>
                                    <div class="small text-secondary">{{ $group->teacher?->full_name ?? '—' }}@if ($group->schedule) · {{ $group->schedule }}@endif</div>
                                </div>
                                <span @class(['badge', 'text-bg-success' => $done, 'text-bg-light border' => ! $done])>{{ $done ? __('courses.taken') : __('courses.not_taken') }}</span>
                            </div>
                            <div class="small mt-2">{{ __('courses.participants_count') }}: @fa($group->active_participants_count)</div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="{{ route('courses.attendance.show', [$group, 'date' => $date->format('Y-m-d')]) }}" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-clipboard-user" aria-hidden="true"></i> {{ __('courses.open_sheet') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
