@extends('layouts.app', ['title' => $book->exists ? __('library.edit_book') : __('library.add_book')])

@php($t = 'library.')

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $book->exists ? __($t.'edit_book').' — '.$book->title : __($t.'add_book') }}</h1>
        <a href="{{ $book->exists ? route('library.catalog.show', $book) : route('library.catalog.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ $book->exists ? route('library.catalog.update', $book) : route('library.catalog.store') }}" novalidate>
        @csrf
        @if ($book->exists) @method('PUT') @endif

        <div class="card mb-3">
            <div class="card-header fw-medium"><i class="fa-solid fa-book" aria-hidden="true"></i> {{ __($t.'book_info') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-8" name="title" :label="__($t.'title')" :value="$book->title" required maxlength="200" :placeholder="__($t.'ph.title')" />
                    <x-form.input class="col-md-4" name="call_number" :label="__($t.'call_number')" :value="$book->call_number" maxlength="30" :placeholder="__($t.'ph.call_number')" :help="__($t.'help.call_number')" />
                    <x-form.input class="col-md-4" name="author" :label="__($t.'author')" :value="$book->author" maxlength="150" :placeholder="__($t.'ph.author')" />
                    <x-form.input class="col-md-4" name="translator" :label="__($t.'translator')" :value="$book->translator" maxlength="150" :placeholder="__($t.'ph.translator')" />
                    <x-form.input class="col-md-4" name="publisher" :label="__($t.'publisher')" :value="$book->publisher" maxlength="150" :placeholder="__($t.'ph.publisher')" />
                    <x-form.input class="col-6 col-md-3" name="publish_year" :label="__($t.'publish_year')" :value="$book->publish_year" inputmode="numeric" :placeholder="__($t.'ph.publish_year')" :help="__($t.'help.publish_year')" />
                    <x-form.input class="col-6 col-md-3" name="edition" :label="__($t.'edition')" :value="$book->edition" maxlength="50" :placeholder="__($t.'ph.edition')" />
                    <x-form.input class="col-6 col-md-3" name="isbn" :label="__($t.'isbn')" :value="$book->isbn" ltr maxlength="20" :placeholder="__($t.'ph.isbn')" :help="__($t.'help.isbn')" />
                    <x-form.input class="col-6 col-md-3" name="pages" :label="__($t.'pages')" :value="$book->pages" inputmode="numeric" :placeholder="__($t.'ph.pages')" />
                    <x-form.select class="col-md-4" name="category" :label="__($t.'category')" :options="\App\Enums\BookCategory::options()" :value="$book->category" required :help="__($t.'help.category')" />
                    <x-form.select class="col-md-4" name="language" :label="__($t.'language')" :options="\App\Enums\BookLanguage::options()" :value="$book->language" required />
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_reference" value="0">
                            <input class="form-check-input" type="checkbox" name="is_reference" value="1" id="is_reference" @checked(old('is_reference', $book->is_reference))>
                            <label class="form-check-label" for="is_reference">{{ __($t.'is_reference') }}</label>
                            <div class="form-text">{{ __($t.'help.is_reference') }}</div>
                        </div>
                    </div>
                    <x-form.select class="col-md-4" name="grade_level_id" :label="__($t.'grade')" :options="$grades" :value="$book->grade_level_id" :placeholder="__($t.'none_selected')" :help="__($t.'help.grade')" />
                    <x-form.select class="col-md-4" name="subject_id" :label="__($t.'subject')" :options="$subjects" :value="$book->subject_id" :placeholder="__($t.'none_selected')" />
                    <x-form.input class="col-md-4" name="keywords" :label="__($t.'keywords')" :value="$book->keywords" maxlength="300" :placeholder="__($t.'ph.keywords')" :help="__($t.'help.keywords')" />
                    <x-form.textarea class="col-12" name="notes" :label="__($t.'notes')" :value="$book->notes" rows="2" maxlength="500" :placeholder="__($t.'ph.notes')" />
                </div>
            </div>
        </div>

        @unless ($book->exists)
            <div class="card mb-3">
                <div class="card-header fw-medium"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ __($t.'copies_info') }}</div>
                <div class="card-body">
                    @include('library.catalog._copy-fields', ['prefix' => 'new', 'withCount' => true, 'open' => true])
                </div>
            </div>
        @endunless

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
    </form>
@endsection
