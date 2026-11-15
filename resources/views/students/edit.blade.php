@extends('layouts.app', ['title' => __('students.edit')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('students.edit') }}: {{ $student->full_name }}</h1>
        <a href="{{ route('students.show', $student) }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('students._form')
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            <a href="{{ route('students.show', $student) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
