@extends('layouts.app', ['title' => $canManage ? __('academics.sections') : __('academics.my_sections')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">
            {{ $canManage ? __('academics.sections') : __('academics.my_sections') }}
            @if ($year)<span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif
        </h1>
        <div class="d-flex gap-2 flex-wrap">
            @if ($years->count() > 1)
                <form method="GET" class="d-flex gap-2">
                    <select name="year" class="form-select" aria-label="{{ __('academics.year') }}">
                        @foreach ($years as $y)
                            <option value="{{ $y->id }}" @selected($year && $y->id === $year->id)>{{ __('academics.year') }} @fa($y->name) @if ($y->is_current)({{ __('academics.current') }})@endif</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary">{{ __('app.view') }}</button>
                </form>
            @endif
            @if ($canManage && $year)
                <a href="{{ route('academics.sections.create', ['year' => $year->id]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('academics.section_create') }}</a>
            @endif
        </div>
    </div>

    @if (! $year)
        <div class="card card-body text-center py-5">
            <div class="fs-5 fw-bold mb-1">{{ __('academics.no_year') }}</div>
            <div class="text-secondary mb-3">{{ __('academics.no_year_hint') }}</div>
            @can('academics.manage')
                <div><a href="{{ route('academics.years.create') }}" class="btn btn-primary">{{ __('academics.year_create') }}</a></div>
            @endcan
        </div>
    @else
        @if ($unassignedCount > 0)
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                {{ __('academics.unassigned_notice', ['count' => \App\Support\Digits::toPersian($unassignedCount)]) }}
            </div>
        @endif

        @if ($sectionsByGrade->isEmpty())
            <div class="card card-body text-center text-secondary py-5">
                {{ $canManage ? __('academics.no_sections') : __('academics.no_my_sections') }}
            </div>
        @else
            <div class="section-grid">
                @foreach ($sectionsByGrade->flatten() as $section)
                    <a href="{{ route('academics.sections.show', $section) }}" class="section-card">
                        <div class="section-card-head">
                            <div>
                                <div class="fw-bold">{{ $section->gradeLevel->name }}</div>
                                <div class="small text-secondary">{{ $section->shift->label() }}@if ($section->room) · {{ $section->room }}@endif</div>
                            </div>
                            <span class="section-badge">{{ $section->name }}</span>
                        </div>
                        <div class="section-card-count">
                            <i class="fa-solid fa-user-graduate" aria-hidden="true"></i>
                            <span class="fs-5">@fa($section->active_enrollments_count)</span> {{ __('academics.students_label') }}
                            @if ($section->capacity)<span class="small text-secondary fw-normal">({{ __('academics.capacity') }} @fa($section->capacity))</span>@endif
                        </div>
                        <div class="small text-secondary text-truncate">
                            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                            {{ $section->classTeacher?->full_name ?? __('academics.no_class_teacher') }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endsection
