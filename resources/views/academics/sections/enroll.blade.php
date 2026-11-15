@extends('layouts.app', ['title' => __('academics.enroll_students')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('academics.enroll_students') }}: {{ $section->label }}</h1>
        <a href="{{ route('academics.sections.show', $section) }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    @if ($students->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_unenrolled') }}</div>
    @else
        <form method="POST" action="{{ route('academics.sections.enroll.store', $section) }}" class="card form-section" data-check-group>
            @csrf
            <div class="card-header"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('academics.enroll_hint') }}</div>
            <div class="card-body pb-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <input type="search" class="form-control" placeholder="{{ __('academics.enroll_filter_ph') }}" data-filter-rows="#enroll-table" aria-label="{{ __('app.search') }}">
                    </div>
                    <div class="col-md-6 text-md-start">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-check-all="student_ids[]">{{ __('academics.select_all') }}</button>
                    </div>
                </div>
                @error('student_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @error('section_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
            <div class="table-responsive enroll-list">
                <table class="table table-hover align-middle mb-0" id="enroll-table">
                    <thead>
                    <tr>
                        <th style="width: 48px"></th>
                        <th>{{ __('students.full_name') }}</th>
                        <th>{{ __('students.father_name') }}</th>
                        <th>{{ __('students.admission_no') }}</th>
                        <th>{{ __('students.gender') }}</th>
                        <th>{{ __('students.status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($students as $student)
                        <tr data-search="{{ $student->full_name }} {{ $student->father_name }} {{ $student->admission_no }}">
                            <td><input class="form-check-input" type="checkbox" name="student_ids[]" value="{{ $student->id }}" id="st{{ $student->id }}" @checked(in_array($student->id, old('student_ids', [])))></td>
                            <td><label for="st{{ $student->id }}" class="mb-0 fw-medium">{{ $student->full_name }}</label></td>
                            <td>{{ $student->father_name }}</td>
                            <td>@fa($student->admission_no)</td>
                            <td>{{ $student->gender->studentLabel() }}</td>
                            <td><span class="badge {{ $student->status->badgeClass() }}">{{ $student->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('academics.enroll_students') }}</button>
            </div>
        </form>
    @endif
@endsection
