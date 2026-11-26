@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('timetable.'.$kind.'_title')])

@php($param = $kind === 'teacher' ? 'staff' : 'room')
@php($name = $current ? ($kind === 'teacher' ? $current->full_name : $current->name) : '')

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('timetable.'.$kind.'_title') }} — {{ $name }}@if ($year) — {{ __('academics.year') }} @fa($year->name)@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('timetable.'.$kind.'_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
            @if ($current)
                <a href="{{ route('timetable.'.$kind.'s.index', [$param => $current->id, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
            @endif
        </div>
        @if ($options->count() > 1 || ($canManage && $options->isNotEmpty()))
            <form method="GET" class="card card-body mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-8 col-lg-5">
                        <label for="tt-pick" class="form-label">{{ __('timetable.'.$kind) }}</label>
                        <select name="{{ $param }}" id="tt-pick" class="form-select">
                            @foreach ($options as $o)<option value="{{ $o->id }}" @selected($current && $current->id === $o->id)>{{ $kind === 'teacher' ? $o->full_name : $o->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-4 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.view') }}</button></div>
                </div>
            </form>
        @endif
    @endif

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @elseif (! $current)
        <div class="card card-body text-center text-secondary py-5">
            {{ __('timetable.no_'.$kind.'s') }}
            @if ($kind === 'room' && $canManage)<div class="mt-2"><a href="{{ route('timetable.setup.index') }}">{{ __('timetable.go_setup') }}</a></div>@endif
        </div>
    @elseif ($grid === [])
        <div class="card card-body text-center text-secondary py-5">{{ __('timetable.'.$kind.'_empty', ['name' => $name]) }}</div>
    @else
        <div class="small mb-2">{{ __('timetable.hours_per_week', ['count' => \App\Support\Digits::toPersian((string) $hours)]) }}</div>
        <div @class(['card' => ! $print, 'print-landscape' => $print])>
            @include('timetable._grid', ['mode' => $kind, 'editable' => false])
        </div>
    @endif
@endsection
