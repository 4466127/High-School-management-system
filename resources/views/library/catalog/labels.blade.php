{{-- برچسب پشت جلد/داخل جلد: نام کتابخانه، نمبر ثبت (درشت)، جای کتاب، عنوان. هر صفحه‌ی A4 حدود ۲۴ برچسب. --}}
@extends('layouts.print', ['title' => __('library.labels_title'), 'noHeader' => true])

@php($name = \App\Http\Controllers\Library\ReportController::libraryName())

@section('content')
    <p class="noprint small text-secondary">{{ __('library.labels_hint') }}</p>
    <div class="book-labels">
        @foreach ($copies as $c)
            <div class="book-label">
                <div class="book-label-lib">{{ $name }}</div>
                <div class="book-label-no">@fa($c->accession_no)</div>
                @if ($book->call_number)<div class="book-label-call">@fa($book->call_number)</div>@endif
                <div class="book-label-title">{{ \Illuminate\Support\Str::limit($book->title, 40) }}</div>
            </div>
        @endforeach
    </div>
@endsection
