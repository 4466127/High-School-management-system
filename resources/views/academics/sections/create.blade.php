@extends('layouts.app', ['title' => __('academics.section_create')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('academics.section_create') }}</h1>
        <a href="{{ route('academics.sections.index', ['year' => $year->id]) }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('academics.sections.store') }}" novalidate>
        @csrf
        @include('academics.sections._form')
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        <a href="{{ route('academics.sections.index', ['year' => $year->id]) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
    </form>
@endsection
