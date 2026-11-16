@extends('layouts.app', ['title' => __('courses.title')])

@php($m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents)))

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('courses.title') }}</h1>
        @can('manage', \App\Models\Course::class)
            <a href="{{ route('courses.list.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('courses.create') }}</a>
        @endcan
    </div>
    <p class="text-secondary small">{{ __('courses.intro') }}</p>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-sm-5 col-lg-3">
                <label for="c-status" class="form-label">{{ __('courses.status') }}</label>
                <select name="status" id="c-status" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach (\App\Enums\CourseStatus::options() as $v => $l)<option value="{{ $v }}" @selected($filters['status'] === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-5 col-lg-3">
                <label for="c-season" class="form-label">{{ __('courses.season') }}</label>
                <select name="season" id="c-season" class="form-select">
                    <option value="">{{ __('courses.all_seasons') }}</option>
                    @foreach (\App\Enums\CourseSeason::options() as $v => $l)<option value="{{ $v }}" @selected($filters['season'] === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-2 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('courses.name') }}</th>
                    <th>{{ __('courses.field') }}</th>
                    <th>{{ __('courses.season') }}</th>
                    <th>{{ __('courses.dates') }}</th>
                    <th class="text-center">{{ __('courses.groups_count') }}</th>
                    <th class="text-center">{{ __('courses.participants_count') }}</th>
                    <th>{{ __('courses.status') }}</th>
                    @if ($canLedger)<th class="text-end">{{ __('courses.balance') }}</th>@endif
                </tr>
                </thead>
                <tbody>
                @forelse ($courses as $course)
                    <tr>
                        <td class="fw-medium"><a href="{{ route('courses.list.show', $course) }}" class="text-decoration-none">{{ $course->name }}</a></td>
                        <td>{{ $course->field }}</td>
                        <td class="small">{{ $course->season->label() }}</td>
                        <td class="small text-nowrap">@jdate($course->start_date) ← @if ($course->end_date)@jdate($course->end_date)@else{{ __('courses.open_ended') }}@endif</td>
                        <td class="text-center">@fa($course->groups_count)</td>
                        <td class="text-center">@fa($course->active_participants_count)</td>
                        <td><span class="badge {{ $course->status->badgeClass() }}">{{ $course->status->label() }}</span></td>
                        @if ($canLedger)
                            @php($balance = $summaries->get($course->id)['balance'] ?? 0)
                            <td class="text-end"><a href="{{ route('courses.ledger.show', $course) }}" @class(['text-decoration-none fw-medium', 'text-danger' => $balance < 0])>{{ $m($balance) }}</a></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-5">{{ auth()->user()->can('manage', \App\Models\Course::class) || $canLedger ? __('courses.none') : __('courses.none_mine') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
