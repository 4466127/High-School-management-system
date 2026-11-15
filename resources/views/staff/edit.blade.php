@extends('layouts.app', ['title' => __('staff.edit')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('staff.edit') }}: {{ $member->full_name }}</h1>
        <a href="{{ route('staff.show', $member) }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('staff.update', $member) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('staff._form')
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            <a href="{{ route('staff.show', $member) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
