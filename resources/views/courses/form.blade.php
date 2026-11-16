@extends('layouts.app', ['title' => $course->exists ? __('courses.edit') : __('courses.create')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $course->exists ? __('courses.edit').' — '.$course->name : __('courses.create') }}</h1>
        <a href="{{ $course->exists ? route('courses.list.show', $course) : route('courses.list.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ $course->exists ? route('courses.list.update', $course) : route('courses.list.store') }}" class="card form-section" novalidate>
        @csrf
        @if ($course->exists) @method('PUT') @endif
        <div class="card-header"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> {{ __('courses.section_course') }}</div>
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-6 mb-3" name="name" :label="__('courses.name')" :value="$course->name" :placeholder="__('courses.name_ph')" required maxlength="150" />
                <x-form.input class="col-md-6 mb-3" name="field" :label="__('courses.field')" :value="$course->field" :placeholder="__('courses.field_ph')" required maxlength="100" list="course-fields" />
                <datalist id="course-fields">
                    @foreach (__('courses.field_suggestions') as $f)<option value="{{ $f }}"></option>@endforeach
                </datalist>
                <x-form.select class="col-md-4 mb-3" name="season" :label="__('courses.season')" :options="\App\Enums\CourseSeason::options()" :value="$course->season" required />
                <x-jalali-date class="col-md-4 mb-3" name="start_date" :label="__('courses.start_date')" :value="$course->start_date" required />
                <x-jalali-date class="col-md-4 mb-3" name="end_date" :label="__('courses.end_date')" :value="$course->end_date" :help="__('courses.end_hint')" />
                <x-form.select class="col-md-4 mb-3" name="status" :label="__('courses.status')" :options="\App\Enums\CourseStatus::options()" :value="$course->status" required />
                <x-form.textarea class="col-12 mb-0" name="description" :label="__('courses.description')" :value="$course->description" rows="3" maxlength="2000" />
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        </div>
    </form>
@endsection
