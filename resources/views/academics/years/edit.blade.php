@extends('layouts.app', ['title' => __('academics.year_edit')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">
            {{ __('academics.year') }} @fa($year->name)
            @if ($year->is_current)<span class="badge text-bg-success fs-6">{{ __('academics.current') }}</span>@endif
        </h1>
        <a href="{{ route('academics.years.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('academics.years.update', $year) }}" class="card form-section mb-3" novalidate>
        @csrf
        @method('PUT')
        <div class="card-header"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> {{ __('academics.year_edit') }}</div>
        <div class="card-body">
            @include('academics.years._form')
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        </div>
    </form>

    <div class="card form-section">
        <div class="card-header justify-content-between">
            <span class="d-flex align-items-center gap-2"><i class="fa-solid fa-list-ol" aria-hidden="true"></i> {{ __('academics.terms') }}</span>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#termAdd"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('academics.term_add') }}</button>
        </div>
        <div class="card-body pb-0"><p class="form-text mt-0">{{ __('academics.terms_hint') }}</p></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('academics.term_order') }}</th>
                    <th>{{ __('academics.term_name') }}</th>
                    <th>{{ __('academics.start_date') }}</th>
                    <th>{{ __('academics.end_date') }}</th>
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($year->terms as $term)
                    <tr>
                        <td>@fa($term->sort_order)</td>
                        <td class="fw-medium">{{ $term->name }}</td>
                        <td>@jdate($term->start_date)</td>
                        <td>@jdate($term->end_date)</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#termEdit{{ $term->id }}">{{ __('app.edit') }}</button>
                            <form method="POST" action="{{ route('academics.years.terms.destroy', [$year, $term]) }}" class="d-inline" data-confirm="{{ __('academics.confirm_term_remove') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('app.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-3">{{ __('academics.no_terms') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @php($nextOrder = ($year->terms->max('sort_order') ?? 0) + 1)
    <x-modal-form id="termAdd" :title="__('academics.term_add')" :action="route('academics.years.terms.store', $year)" :submit="__('app.add')">
        @include('academics.years._term-fields', ['term' => null, 'own' => old('_modal') === 'termAdd', 'order' => $nextOrder, 'prefix' => 'termAdd'])
    </x-modal-form>
    @foreach ($year->terms as $term)
        @php($modalId = 'termEdit'.$term->id)
        <x-modal-form :id="$modalId" :title="__('app.edit').': '.$term->name" :action="route('academics.years.terms.update', [$year, $term])" method="PUT" :submit="__('app.save')">
            @include('academics.years._term-fields', ['term' => $term, 'own' => old('_modal') === $modalId, 'order' => $term->sort_order, 'prefix' => $modalId])
        </x-modal-form>
    @endforeach
@endsection
