@extends('layouts.app', ['title' => __('finance.setup_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('finance.setup_title') }}</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeTypeCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('finance.type_create') }}</button>
    </div>

    <h2 class="section-title">{{ __('finance.types_title') }}</h2>
    @if ($types->isEmpty())
        <div class="card card-body text-center text-secondary py-4 mb-4">{{ __('finance.no_types') }}</div>
    @else
        <div class="card mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('finance.type_name') }}</th>
                        <th>{{ __('finance.period') }}</th>
                        <th class="text-center">{{ __('finance.sort_order') }}</th>
                        <th>{{ __('finance.status') }}</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($types as $type)
                        <tr @class(['text-secondary' => ! $type->is_active])>
                            <td class="fw-medium">{{ $type->name }}</td>
                            <td>{{ $type->period->label() }}</td>
                            <td class="text-center">@fa($type->sort_order)</td>
                            <td>
                                @if ($type->is_active)<span class="badge text-bg-success">{{ __('finance.active') }}</span>
                                @else<span class="badge text-bg-light border">{{ __('finance.inactive') }}</span>@endif
                            </td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#feeTypeEdit{{ $type->id }}">{{ __('app.edit') }}</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <h2 class="section-title">{{ __('finance.rates_title') }}</h2>
    @if (! $year)
        <div class="card card-body text-center text-secondary py-4">{{ __('academics.no_year') }}</div>
    @elseif ($types->isNotEmpty())
        <form method="POST" action="{{ route('finance.setup.rates') }}" class="card">
            @csrf
            @method('PUT')
            <div class="card-body pb-0"><p class="small text-secondary">{{ __('finance.rates_hint', ['year' => \App\Support\Digits::toPersian($year->name)]) }}</p></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 rates-table">
                    <thead>
                    <tr>
                        <th>{{ __('academics.grade') }}</th>
                        @foreach ($types as $type)<th>{{ $type->name }} <span class="small fw-normal text-secondary">({{ $type->period->label() }})</span></th>@endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($grades as $grade)
                        <tr>
                            <td class="fw-medium text-nowrap">{{ $grade->name }}</td>
                            @foreach ($types as $type)
                                <td>
                                    <input type="text" inputmode="decimal" dir="ltr" class="form-control form-control-sm rate-input"
                                           name="rates[{{ $type->id }}][{{ $grade->id }}]" value="{{ old('rates.'.$type->id.'.'.$grade->id, $rates[$type->id][$grade->id] ?? '') }}"
                                           placeholder="—" aria-label="{{ $type->name }} — {{ $grade->name }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            </div>
        </form>
    @endif

    <x-modal-form id="feeTypeCreate" :title="__('finance.type_create')" :action="route('finance.setup.types.store')" :submit="__('app.save')">
        @include('finance.setup._type-fields', ['type' => null, 'prefix' => 'ftc-', 'useOld' => old('_modal') === 'feeTypeCreate'])
    </x-modal-form>
    @foreach ($types as $type)
        <x-modal-form id="feeTypeEdit{{ $type->id }}" :title="__('finance.type_edit')" :action="route('finance.setup.types.update', $type)" method="PUT" :submit="__('app.save')">
            @include('finance.setup._type-fields', ['type' => $type, 'prefix' => 'fte'.$type->id.'-', 'useOld' => old('_modal') === 'feeTypeEdit'.$type->id])
        </x-modal-form>
    @endforeach
@endsection
