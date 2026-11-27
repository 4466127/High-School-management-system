@extends('layouts.app', ['title' => __('teaching.title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('teaching.title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
        @if ($review)
            <a href="{{ route('teaching.reports.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> {{ __('teaching.reports_title') }}</a>
        @endif
    </div>
    <div class="alert alert-light border small">
        <div class="fw-medium mb-1"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('teaching.how_title') }}</div>
        <ol class="mb-1 ps-3">@foreach (__('teaching.how_steps') as $step)<li>{{ $step }}</li>@endforeach</ol>
        <div>{{ $review ? __('teaching.intro_manager') : __('teaching.intro_teacher') }}</div>
    </div>

    @if ($noStaff)
        <div class="alert alert-warning">{{ __('teaching.no_staff') }}</div>
    @elseif (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @else
        @php($n = fn ($v) => \App\Support\Digits::toPersian((string) $v))
        <div class="d-flex flex-wrap gap-2 mb-3 small">
            <a href="{{ route('teaching.plans.index') }}" class="badge text-bg-light border text-decoration-none fs-6 fw-normal">{{ __('teaching.sum_total', ['count' => $n($summary['total'])]) }}</a>
            <a href="{{ route('teaching.plans.index', ['status' => 'none']) }}" class="badge text-bg-secondary text-decoration-none fs-6 fw-normal">{{ __('teaching.sum_none', ['count' => $n($summary['none'])]) }}</a>
            <a href="{{ route('teaching.plans.index', ['status' => 'submitted']) }}" class="badge text-bg-info text-decoration-none fs-6 fw-normal">{{ __('teaching.sum_submitted', ['count' => $n($summary['submitted'])]) }}</a>
            <a href="{{ route('teaching.plans.index', ['status' => 'approved']) }}" class="badge text-bg-success text-decoration-none fs-6 fw-normal">{{ __('teaching.sum_approved', ['count' => $n($summary['approved'])]) }}</a>
            <a href="{{ route('teaching.plans.index', ['status' => 'overdue']) }}" class="badge text-bg-warning text-decoration-none fs-6 fw-normal">{{ __('teaching.sum_overdue', ['count' => $n($summary['overdue'])]) }}</a>
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="tp-status" class="form-label">{{ __('teaching.filter') }}</label>
                    <select name="status" id="tp-status" class="form-select" data-submit-on-change>
                        <option value="">{{ __('teaching.filter_all') }}</option>
                        @foreach (\App\Http\Controllers\Teaching\PlanController::statusFilters() as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="tp-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="tp-section" class="form-select" data-submit-on-change>
                        <option value="">{{ __('teaching.filter_all') }}</option>
                        @foreach ($sectionOptions as $s)<option value="{{ $s->id }}" @selected($filters['section'] === $s->id)>{{ $s->label }}</option>@endforeach
                    </select>
                </div>
                @if ($review)
                    <div class="col-sm-6 col-lg-2">
                        <label for="tp-staff" class="form-label">{{ __('timetable.teacher') }}</label>
                        <select name="staff" id="tp-staff" class="form-select" data-submit-on-change>
                            <option value="">{{ __('teaching.filter_all') }}</option>
                            @foreach ($teacherOptions as $tch)<option value="{{ $tch->id }}" @selected($filters['staff'] === $tch->id)>{{ $tch->full_name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="col-sm-6 col-lg-2">
                    <label for="tp-q" class="form-label">{{ __('app.search') }}</label>
                    <input type="search" name="q" id="tp-q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('teaching.search_ph') }}">
                </div>
                <div class="col-sm-6 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr>
                        <th>{{ __('academics.section') }}</th><th>{{ __('academics.subject') }}</th>
                        @if ($review)<th>{{ __('timetable.teacher') }}</th>@endif
                        <th>{{ __('teaching.status') }}</th><th style="min-width: 160px">{{ __('teaching.progress') }}</th><th></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $row)
                        @php($a = $row['assignment'])
                        @php($p = $row['progress'])
                        <tr>
                            <td>{{ $a->section->label }}</td>
                            <td class="fw-medium">{{ $a->subject->name }}</td>
                            @if ($review)<td class="small">{{ $a->teacher?->full_name ?? '—' }}</td>@endif
                            <td>
                                @if ($row['plan'])<span class="badge {{ $row['plan']->status->badgeClass() }}">{{ $row['plan']->status->label() }}</span>
                                @else<span class="small text-secondary">{{ __('teaching.no_plan') }}</span>@endif
                            </td>
                            <td>
                                @if ($p && $p['total'] > 0)
                                    <div class="progress" role="progressbar" aria-valuenow="{{ $p['percent'] }}" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                                        <div class="progress-bar @if ($p['overdue']) bg-warning @endif" style="width: {{ $p['percent'] }}%"></div>
                                    </div>
                                    <div class="small text-secondary">@fa($p['percent'])٪ — {{ __('teaching.done_of', ['done' => \App\Support\Digits::toPersian((string) $p['done']), 'total' => \App\Support\Digits::toPersian((string) $p['total'])]) }}
                                        @if ($p['overdue']) · <span class="text-danger">{{ __('teaching.overdue_n', ['count' => \App\Support\Digits::toPersian((string) $p['overdue'])]) }}</span>@endif</div>
                                @else
                                    <span class="small text-secondary">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($row['plan'])
                                    <a href="{{ route('teaching.plans.show', $row['plan']) }}" class="btn btn-sm btn-outline-primary">{{ __('teaching.open') }}</a>
                                @else
                                    <form method="POST" action="{{ route('teaching.plans.open') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="section" value="{{ $a->section_id }}">
                                        <input type="hidden" name="subject" value="{{ $a->subject_id }}">
                                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('teaching.create') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">{{ $review ? __('teaching.no_assignments_manager') : __('teaching.no_assignments') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $rows->links() }}</div>
    @endif
@endsection
