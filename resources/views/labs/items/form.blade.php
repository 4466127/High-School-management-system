@extends('layouts.app', ['title' => $item->exists ? __('labs.edit_item') : __('labs.add_item')])

@php($t = 'labs.')

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $item->exists ? __($t.'edit_item').' — '.$item->name : __($t.'add_item') }}</h1>
        <a href="{{ $item->exists ? route('labs.items.show', $item) : route('labs.items.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    @if ($labs === [])
        <div class="alert alert-warning">{{ __($t.'no_labs') }} <a href="{{ route('labs.setup.index') }}">{{ __('menu.labs_setup') }}</a></div>
    @endif

    <form method="POST" action="{{ $item->exists ? route('labs.items.update', $item) : route('labs.items.store') }}" class="card" novalidate>
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row">
                <x-form.select class="col-md-4" name="lab_id" :label="__($t.'lab')" :options="$labs" :value="$item->lab_id" required :placeholder="__($t.'choose_lab')" />
                <x-form.input class="col-md-5" name="name" :label="__($t.'name')" :value="$item->name" required maxlength="150" :placeholder="__($t.'ph.name')" />
                <x-form.input class="col-md-3" name="code" :label="__($t.'code')" :value="$item->code" maxlength="30" :placeholder="__($t.'ph.code')" :help="__($t.'help.code')" />
                <x-form.select class="col-6 col-md-3" name="kind" :label="__($t.'kind')" :options="\App\Enums\LabItemKind::options()" :value="$item->kind" required :help="__($t.'help.kind')" />
                <x-form.select class="col-6 col-md-3" name="unit" :label="__($t.'unit')" :options="\App\Enums\LabUnit::options()" :value="$item->unit" required :help="__($t.'help.unit')" />
                @unless ($item->exists)
                    <x-form.input class="col-6 col-md-3" name="quantity" :label="__($t.'initial_quantity')" inputmode="decimal" :placeholder="__($t.'ph.quantity')" :help="__($t.'help.initial_quantity')" />
                @endunless
                <x-form.input class="col-6 col-md-3" name="min_quantity" :label="__($t.'min_quantity')" :value="$item->min_quantity !== null ? \App\Support\Quantity::plain($item->min_quantity) : null" inputmode="decimal" :placeholder="__($t.'ph.min_quantity')" :help="__($t.'help.min_quantity')" />
                <x-form.input class="col-6 col-md-3" name="location" :label="__($t.'location')" :value="$item->location" maxlength="50" :placeholder="__($t.'ph.location')" />
                <x-form.select class="col-6 col-md-3" name="hazard" :label="__($t.'hazard')" :options="\App\Enums\ChemicalHazard::options()" :value="$item->hazard" :placeholder="__($t.'no_hazard')" :help="__($t.'help.hazard')" />
                <x-jalali-date class="col-6 col-md-3" name="expires_on" :label="__($t.'expires_on')" :value="$item->expires_on" :help="__($t.'help.expires_on')" />
                <x-form.input class="col-6 col-md-3" name="unit_price" :label="__($t.'unit_price')" :value="$item->unit_price !== null ? \App\Support\Money::plain($item->unit_price) : null" inputmode="decimal" :placeholder="__($t.'ph.unit_price')" :help="__($t.'help.unit_price')" />
                <x-form.select class="col-6 col-md-3" name="source" :label="__($t.'source')" :options="\App\Enums\CopySource::options()" :value="$item->source" required />
                <x-form.input class="col-md-9" name="specification" :label="__($t.'specification')" :value="$item->specification" maxlength="300" :placeholder="__($t.'ph.specification')" />
                <x-form.textarea class="col-12" name="notes" :label="__($t.'notes')" :value="$item->notes" rows="2" maxlength="500" :placeholder="__($t.'ph.notes')" />
                @if ($item->exists)
                    <div class="col-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $item->is_active))>
                            <label class="form-check-label" for="is_active">{{ __('app.active') }}</label>
                            <div class="form-text">{{ __($t.'help.inactive') }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection
