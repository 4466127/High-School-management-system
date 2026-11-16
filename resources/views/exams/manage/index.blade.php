@extends('layouts.app', ['title' => __('exams.manage_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('exams.manage_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
        @if ($year)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('exams.create') }}</button>
        @endif
    </div>

    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('exams.manage_intro') }}</div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#exams-pane" type="button" role="tab">{{ __('exams.tab_exams') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#types-pane" type="button" role="tab">{{ __('exams.tab_types') }}</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="exams-pane" role="tabpanel">
            @if (! $year)
                <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
            @elseif ($exams->isEmpty())
                <div class="card card-body text-center py-5">
                    <div class="text-secondary mb-3">{{ __('exams.no_exams') }}</div>
                    <div><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examCreate">{{ __('exams.create') }}</button></div>
                </div>
            @else
                @php($weights = $exams->where('counts_in_final', true)->sum(fn ($e) => (float) $e->weight))
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('exams.name') }}</th>
                                <th>{{ __('exams.term') }}</th>
                                <th>{{ __('exams.dates') }}</th>
                                <th class="text-center">{{ __('exams.max_mark') }}</th>
                                <th class="text-center">{{ __('exams.weight') }}</th>
                                <th class="text-center">{{ __('exams.counts_short') }}</th>
                                <th>{{ __('exams.status') }}</th>
                                <th class="text-center">{{ __('exams.marks_count') }}</th>
                                <th class="text-end">{{ __('app.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($exams as $exam)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $exam->name }}</div>
                                        <div class="small text-secondary">{{ $exam->type->name }}</div>
                                    </td>
                                    <td class="small">{{ $exam->term?->name ?? '—' }}</td>
                                    <td class="small text-nowrap">
                                        @if ($exam->start_date)@jdate($exam->start_date)@endif
                                        @if ($exam->end_date) ← @jdate($exam->end_date)@endif
                                    </td>
                                    <td class="text-center">@fa(\App\Services\MarkService::format($exam->max_mark))</td>
                                    <td class="text-center">@fa(\App\Services\MarkService::format($exam->weight))</td>
                                    <td class="text-center">
                                        @if ($exam->counts_in_final)<i class="fa-solid fa-check text-success" title="{{ __('app.yes') }}"></i>@else<span class="text-secondary">—</span>@endif
                                    </td>
                                    <td><span class="badge {{ $exam->status->badgeClass() }}">{{ $exam->status->label() }}</span></td>
                                    <td class="text-center">@fa($exam->marks_count)</td>
                                    <td class="text-end text-nowrap">
                                        @foreach (['draft' => ['open'], 'open' => ['locked', 'draft'], 'locked' => ['open']][$exam->status->value] as $target)
                                            <form method="POST" action="{{ route('exams.manage.status', $exam) }}" class="d-inline" data-confirm="{{ __('exams.confirm_status_'.$target) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $target }}">
                                                <button type="submit" class="btn btn-sm {{ $target === 'locked' ? 'btn-dark' : ($target === 'open' ? 'btn-success' : 'btn-outline-secondary') }}">
                                                    {{ __('exams.action_'.$target.($exam->status->value === 'locked' ? '_again' : '')) }}
                                                </button>
                                            </form>
                                        @endforeach
                                        <a href="{{ route('exams.manage.edit', $exam) }}" class="btn btn-sm btn-outline-primary">{{ __('app.edit') }}</a>
                                        @if ($exam->marks_count === 0)
                                            <form method="POST" action="{{ route('exams.manage.destroy', $exam) }}" class="d-inline" data-confirm="{{ __('exams.confirm_delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div @class(['small mt-2', 'text-secondary' => $weights > 0, 'text-danger' => $weights <= 0])>
                    {{ __('exams.weights_note', ['sum' => \App\Support\Digits::toPersian(\App\Services\MarkService::format($weights))]) }}
                    @if ($yearFinalized) · <span class="text-warning-emphasis">{{ __('exams.scoring_locked_finalized') }}</span>@endif
                </div>
            @endif
        </div>

        <div class="tab-pane fade" id="types-pane" role="tabpanel">
            <div class="mb-2 text-start">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#typeCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('exams.type_create') }}</button>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('exams.type_name') }}</th>
                            <th class="text-center">{{ __('exams.default_max_mark') }}</th>
                            <th class="text-center">{{ __('exams.default_weight') }}</th>
                            <th class="text-center">{{ __('exams.counts_short') }}</th>
                            <th class="text-center">{{ __('exams.sort_order') }}</th>
                            <th></th>
                            <th class="text-end">{{ __('app.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($types as $type)
                            <tr @class(['text-secondary' => ! $type->is_active])>
                                <td class="fw-medium">{{ $type->name }}</td>
                                <td class="text-center">@fa(\App\Services\MarkService::format($type->default_max_mark))</td>
                                <td class="text-center">@fa(\App\Services\MarkService::format($type->default_weight))</td>
                                <td class="text-center">@if ($type->counts_in_final)<i class="fa-solid fa-check text-success"></i>@else — @endif</td>
                                <td class="text-center">@fa($type->sort_order)</td>
                                <td>@unless ($type->is_active)<span class="badge text-bg-light border">{{ __('exams.inactive') }}</span>@endunless</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#typeEdit{{ $type->id }}">{{ __('app.edit') }}</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- پنجره‌ها --}}
    @if ($year)
        <x-modal-form id="examCreate" :title="__('exams.create')" :action="route('exams.manage.store')" :submit="__('app.save')">
            @include('exams.manage._fields', ['prefix' => 'c-', 'useOld' => old('_modal') === 'examCreate'])
        </x-modal-form>
    @endif

    <x-modal-form id="typeCreate" :title="__('exams.type_create')" :action="route('exams.manage.types.store')" :submit="__('app.save')">
        @include('exams.manage._type-fields', ['type' => null, 'prefix' => 'tc-', 'useOld' => old('_modal') === 'typeCreate'])
    </x-modal-form>
    @foreach ($types as $type)
        <x-modal-form id="typeEdit{{ $type->id }}" :title="__('exams.type_edit')" :action="route('exams.manage.types.update', $type)" method="PUT" :submit="__('app.save')">
            @include('exams.manage._type-fields', ['type' => $type, 'prefix' => 'te'.$type->id.'-', 'useOld' => old('_modal') === 'typeEdit'.$type->id])
        </x-modal-form>
    @endforeach
@endsection
