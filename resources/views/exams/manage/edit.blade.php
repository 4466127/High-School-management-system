@extends('layouts.app', ['title' => __('exams.edit')])

@section('content')
    <h1 class="page-title">{{ __('exams.edit') }} — {{ $exam->name }}</h1>

    @if ($yearFinalized)
        <div class="alert alert-warning">{{ __('exams.scoring_locked_finalized') }}</div>
    @elseif ($exam->marks()->exists())
        <div class="alert alert-info small">{{ __('exams.edit_with_marks_hint') }}</div>
    @endif

    <form method="POST" action="{{ route('exams.manage.update', $exam) }}" class="card form-section" novalidate>
        @csrf
        @method('PUT')
        <div class="card-header"><i class="fa-solid fa-file-pen" aria-hidden="true"></i> {{ __('exams.section_exam') }}</div>
        <div class="card-body">
            @include('exams.manage._fields', ['prefix' => 'e-', 'useOld' => true])
        </div>
        <div class="card-footer bg-transparent d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            <a href="{{ route('exams.manage.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
