@extends('layouts.app', ['title' => __('labs.activities_title')])

@php($t = 'labs.')

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __($t.'activities_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
        <div class="d-flex flex-wrap gap-2">
            @if ($canCreate)<a href="{{ route('labs.activities.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'plan_activity') }}</a>@endif
            <a href="{{ route('labs.schedule.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i> {{ __('menu.labs_schedule') }}</a>
        </div>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'activities_intro') }}</div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="pa-status" class="form-label">{{ __($t.'status') }}</label>
                <select name="status" id="pa-status" class="form-select">
                    @foreach (__($t.'activity_filters') as $v => $l)<option value="{{ $v }}" @selected($filters['status'] === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="pa-lab" class="form-label">{{ __($t.'lab') }}</label>
                <select name="lab" id="pa-lab" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) ($filters['lab'] ?? 0) === $l->id)>{{ $l->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="pa-section" class="form-label">{{ __('academics.section') }}</label>
                <select name="section" id="pa-section" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($sections as $s)<option value="{{ $s->id }}" @selected((int) ($filters['section'] ?? 0) === $s->id)>{{ $s->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                @if ($isTeacher)
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="mine" value="1" id="pa-mine" @checked($filters['mine'] ?? false)>
                        <label class="form-check-label small" for="pa-mine">{{ __($t.'only_mine') }}</label>
                    </div>
                @endif
                <button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __($t.'date') }}</th><th>{{ __($t.'activity_title') }}</th><th>{{ __('academics.section') }}</th><th>{{ __($t.'lab') }}</th><th>{{ __($t.'status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($activities as $a)
                    <tr>
                        <td class="small text-nowrap">@jdate($a->activity_date)@if ($a->period)<div class="text-secondary">{{ $a->period->name }}</div>@endif</td>
                        <td><span class="fw-medium">{{ $a->title }}</span><div class="small text-secondary">{{ $a->subject->name }}@if ($a->teacher) · {{ $a->teacher->full_name }}@endif</div></td>
                        <td class="small">{{ $a->section->gradeLevel->name }} {{ $a->section->name }}</td>
                        <td class="small">{{ $a->lab->name }}</td>
                        <td>
                            <span class="badge {{ $a->status->badgeClass() }}">{{ $a->status->label() }}</span>
                            @if ($a->status === \App\Enums\ActivityStatus::Done)<div class="small text-secondary">{{ __($t.'present_n', ['count' => \App\Support\Digits::toPersian((string) $a->present_count)]) }}</div>
                            @elseif ($a->status === \App\Enums\ActivityStatus::Planned && $a->activity_date->lt(\App\Services\AttendanceService::today()))<div class="small text-danger">{{ __($t.'not_recorded') }}</div>@endif
                        </td>
                        <td class="text-end"><a href="{{ route('labs.activities.show', $a) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __($t.'no_activities') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $activities->links() }}</div>
@endsection
