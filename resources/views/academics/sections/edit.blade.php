@extends('layouts.app', ['title' => __('academics.section_edit')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('academics.section_edit') }}: {{ $section->label }}</h1>
        <a href="{{ route('academics.sections.show', $section) }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('academics.sections.update', $section) }}" novalidate>
        @csrf
        @method('PUT')
        @include('academics.sections._form')
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        <a href="{{ route('academics.sections.show', $section) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
    </form>

    <form method="POST" action="{{ route('academics.sections.destroy', $section) }}" class="mt-4" data-confirm="{{ __('academics.confirm_section_delete') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash" aria-hidden="true"></i> {{ __('app.delete') }}</button>
    </form>
@endsection
