@extends('layouts.app', ['title' => __('academics.years')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('academics.years') }}</h1>
        <a href="{{ route('academics.years.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('academics.year_create') }}</a>
    </div>

    @if ($years->isEmpty())
        <div class="card card-body text-center py-5">
            <div class="fs-5 fw-bold mb-1">{{ __('academics.no_year') }}</div>
            <div class="text-secondary mb-3">{{ __('academics.no_year_hint') }}</div>
            <div><a href="{{ route('academics.years.create') }}" class="btn btn-primary">{{ __('academics.year_create') }}</a></div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('academics.year_name') }}</th>
                        <th>{{ __('academics.start_date') }}</th>
                        <th>{{ __('academics.end_date') }}</th>
                        <th>{{ __('academics.sections_count') }}</th>
                        <th>{{ __('academics.enrollments_count') }}</th>
                        <th class="text-end">{{ __('app.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($years as $year)
                        <tr>
                            <td class="fw-bold">
                                @fa($year->name)
                                @if ($year->is_current)<span class="badge text-bg-success">{{ __('academics.current') }}</span>@endif
                            </td>
                            <td>@jdate($year->start_date, 'long')</td>
                            <td>@jdate($year->end_date, 'long')</td>
                            <td>@fa($year->sections_count)</td>
                            <td>@fa($year->enrollments_count)</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('academics.sections.index', ['year' => $year->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('academics.sections') }}</a>
                                <a href="{{ route('academics.years.edit', $year) }}" class="btn btn-sm btn-outline-primary">{{ __('app.edit') }}</a>
                                @unless ($year->is_current)
                                    <form method="POST" action="{{ route('academics.years.current', $year) }}" class="d-inline" data-confirm="{{ __('academics.confirm_make_current') }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-success">{{ __('academics.make_current') }}</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
