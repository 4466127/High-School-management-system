@extends('layouts.app', ['title' => __('academics.grade_subjects').' — '.$grade->name])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('academics.grade_subjects') }}: {{ $grade->name }}</h1>
        <a href="{{ route('academics.grades.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    @php
        $chosen = $grade->subjects->pluck('pivot.sort_order', 'id');
    @endphp

    <form method="POST" action="{{ route('academics.grades.subjects.update', $grade) }}" class="card form-section">
        @csrf
        @method('PUT')
        <div class="card-header"><i class="fa-solid fa-book-open" aria-hidden="true"></i> {{ __('academics.subjects') }}</div>
        <div class="card-body">
            <p class="form-text mt-0">{{ __('academics.grade_subjects_hint') }}</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width: 48px"></th>
                        <th>{{ __('academics.subject') }}</th>
                        <th style="width: 120px">{{ __('academics.order') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($subjects as $i => $subject)
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="selected[]" value="{{ $subject->id }}" id="s{{ $subject->id }}" @checked($chosen->has($subject->id))></td>
                            <td><label for="s{{ $subject->id }}" class="mb-0">{{ $subject->name }}</label></td>
                            <td><input type="number" name="order[{{ $subject->id }}]" min="1" max="99" class="form-control form-control-sm" value="{{ $chosen[$subject->id] ?? $i + 1 }}"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        </div>
    </form>
@endsection
