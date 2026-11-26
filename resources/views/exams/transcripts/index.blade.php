@extends('layouts.app', ['title' => __('transcripts.title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('transcripts.title') }}</h1>
        @if (auth()->user()->hasPermission('exams.manage'))
            <a href="{{ route('exams.templates.index') }}#transcript" class="btn btn-outline-secondary"><i class="fa-solid fa-sliders" aria-hidden="true"></i> {{ __('exam_templates.title') }}</a>
        @endif
    </div>

    <div class="alert alert-light border small">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ __('transcripts.intro', ['grades' => $levels->pluck('name')->implode('، ')]) }}
        <div class="mt-1">{{ __('transcripts.sources') }}</div>
    </div>

    <div class="row g-3">
        @if ($canSearch)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-medium"><i class="fa-solid fa-user" aria-hidden="true"></i> {{ __('transcripts.one_student') }}</div>
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-2 mb-2">
                            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('transcripts.search_placeholder') }}" aria-label="{{ __('app.search') }}">
                            <button type="submit" class="btn btn-outline-primary text-nowrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('app.search') }}</button>
                        </form>
                        <div class="form-text mb-2">{{ __('transcripts.search_hint') }}</div>
                        @if ($q !== '')
                            <ul class="list-group list-group-flush">
                                @forelse ($students as $s)
                                    <li class="list-group-item d-flex align-items-center gap-2 px-0">
                                        <span class="flex-grow-1">{{ $s->full_name }}
                                            <span class="small text-secondary">— {{ __('students.father_name') }}: {{ $s->father_name }} · @fa($s->admission_no)</span>
                                            <span class="badge {{ $s->status->badgeClass() }}">{{ $s->status->label() }}</span>
                                        </span>
                                        <a href="{{ route('exams.transcripts.student', $s) }}" class="btn btn-sm btn-outline-primary text-nowrap"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('transcripts.print') }}</a>
                                    </li>
                                @empty
                                    <li class="list-group-item px-0 text-secondary">{{ __('transcripts.no_match') }}</li>
                                @endforelse
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-medium"><i class="fa-solid fa-users" aria-hidden="true"></i> {{ __('transcripts.whole_section') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($sections as $section)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <span class="flex-grow-1">{{ $section->label }}</span>
                            <a href="{{ route('exams.transcripts.section', $section) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('exams.print_all') }}</a>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">{{ __('transcripts.no_sections') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
