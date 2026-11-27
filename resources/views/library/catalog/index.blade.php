@extends('layouts.app', ['title' => __('library.catalog_title')])

@php
    $t = 'library.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __($t.'catalog_title') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            @if ($canManage)
                <a href="{{ route('library.catalog.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'add_book') }}</a>
                <a href="{{ route('import.index', ['type' => 'books']) }}" class="btn btn-outline-success"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> {{ __($t.'import_books') }}</a>
            @endif
            <a href="{{ route('library.catalog.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
        </div>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ $canManage ? __($t.'catalog_intro_manager') : __($t.'catalog_intro') }}</div>

    <div class="row g-2 mb-3">
        @foreach (['titles' => 'fa-book', 'copies' => 'fa-layer-group', 'available' => 'fa-circle-check', 'on_loan' => 'fa-arrow-right-arrow-left'] as $key => $icon)
            <div class="col-6 col-lg-3">
                <div class="card card-body py-2 h-100">
                    <div class="small text-secondary"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ __($t.'totals.'.$key) }}</div>
                    <div class="fs-4 fw-bold">{{ $n($totals[$key]) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="lb-q" class="form-label">{{ __('app.search') }}</label>
                <input type="search" name="q" id="lb-q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __($t.'search_ph') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="lb-cat" class="form-label">{{ __($t.'category') }}</label>
                <select name="category" id="lb-cat" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach (\App\Enums\BookCategory::options() as $v => $l)<option value="{{ $v }}" @selected(($filters['category'] ?? '') === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="lb-lang" class="form-label">{{ __($t.'language') }}</label>
                <select name="language" id="lb-lang" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach (\App\Enums\BookLanguage::options() as $v => $l)<option value="{{ $v }}" @selected(($filters['language'] ?? '') === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="lb-grade" class="form-label">{{ __($t.'grade') }}</label>
                <select name="grade" id="lb-grade" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($grades as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['grade'] ?? 0) === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="available" value="1" id="lb-av" @checked(($filters['available'] ?? '') === '1')>
                    <label class="form-check-label small" for="lb-av">{{ __($t.'only_available') }}</label>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>{{ __($t.'title') }}</th><th>{{ __($t.'category') }}</th><th>{{ __($t.'call_number') }}</th>
                    <th class="text-center">{{ __($t.'copies') }}</th><th class="text-center">{{ __($t.'available') }}</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($books as $b)
                    <tr>
                        <td>
                            <a href="{{ route('library.catalog.show', $b) }}" class="fw-medium text-decoration-none">{{ $b->title }}</a>
                            @if ($b->is_reference) <span class="badge text-bg-light border">{{ __($t.'reference_badge') }}</span>@endif
                            <div class="small text-secondary">{{ $b->author ?: '—' }}@if ($b->publisher) · {{ $b->publisher }}@endif @if ($b->publish_year) · @fa($b->publish_year)@endif</div>
                        </td>
                        <td class="small">{{ $b->category->label() }}@if ($b->gradeLevel)<div class="text-secondary">{{ $b->gradeLevel->name }}@if ($b->subject) · {{ $b->subject->name }}@endif</div>@endif</td>
                        <td class="small">@fa($b->call_number ?: '—')</td>
                        <td class="text-center">{{ $n($b->copies_total) }}</td>
                        <td class="text-center">
                            @if ($b->copies_available > 0)<span class="badge text-bg-success">{{ $n($b->copies_available) }}</span>
                            @else<span class="badge text-bg-secondary">{{ $n(0) }}</span>@endif
                        </td>
                        <td class="text-end"><a href="{{ route('library.catalog.show', $b) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ $totals['titles'] ? __('app.no_records') : __($t.'no_books') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $books->links() }}</div>
@endsection
