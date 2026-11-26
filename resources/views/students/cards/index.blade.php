@extends('layouts.app', ['title' => __('cards.title')])

@section('content')
    <h1 class="page-title">{{ __('cards.title') }}</h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('cards.hint') }}</div>

    @if (! $year)
        <div class="alert alert-warning">{{ __('cards.no_year') }}</div>
    @else
        <div class="section-grid">
            @foreach ($sections as $section)
                <div class="section-card">
                    <div class="fw-bold">{{ $section->label }}</div>
                    <div class="small text-secondary">{{ __('cards.students', ['count' => \App\Support\Digits::toPersian($section->active_enrollments_count)]) }}</div>
                    @if ($section->active_enrollments_count > 0)
                        <a href="{{ route('student-cards.index', ['section' => $section->id, 'print' => 1]) }}" class="btn btn-sm btn-outline-primary mt-1"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('cards.print') }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
