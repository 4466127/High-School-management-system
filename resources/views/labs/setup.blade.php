@extends('layouts.app', ['title' => __('labs.setup_title')])

@php($t = 'labs.')

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __($t.'setup_title') }}</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#labNew"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'add_lab') }}</button>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'setup_intro') }}</div>

    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse ($labs as $lab)
                <li @class(['list-group-item d-flex flex-wrap align-items-center gap-2', 'text-secondary' => ! $lab->is_active])>
                    <i class="fa-solid fa-flask text-secondary" aria-hidden="true"></i>
                    <span class="flex-grow-1">
                        <span class="fw-medium">{{ $lab->name }}</span>
                        <span class="small text-secondary">— {{ $lab->type->label() }}@if ($lab->room) · {{ $lab->room->name }}@endif @if ($lab->manager) · {{ __($t.'manager') }}: {{ $lab->manager->full_name }}@endif</span>
                        @unless ($lab->is_active) <span class="badge text-bg-secondary">{{ __('app.inactive') }}</span>@endunless
                    </span>
                    <a href="{{ route('labs.items.index', ['lab' => $lab->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __($t.'items_n', ['count' => \App\Support\Digits::toPersian((string) $lab->items_count)]) }}</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#lab{{ $lab->id }}">{{ __('app.edit') }}</button>
                </li>
            @empty
                <li class="list-group-item text-secondary small py-4 text-center">{{ __($t.'no_labs_yet') }}</li>
            @endforelse
        </ul>
    </div>

    @foreach (array_merge([null], $labs->all()) as $lab)
        @php($id = $lab ? 'lab'.$lab->id : 'labNew')
        @php($open = old('_modal') === $id)
        @php($val = fn ($field, $default) => $open ? old($field, $default) : $default)
        <x-modal-form :id="$id" :title="$lab ? __('app.edit').' — '.$lab->name : __($t.'add_lab')" :action="$lab ? route('labs.setup.update', $lab) : route('labs.setup.store')" :method="$lab ? 'PUT' : 'POST'" :submit="__('app.save')" size="lg">
            <div class="row">
                <x-form.input class="col-md-6" :id="$id.'-name'" name="name" :label="__($t.'lab_name')" :value="$lab?->name" required maxlength="100" :use-old="$open" :placeholder="__($t.'ph.lab_name')" />
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="{{ $id }}-type">{{ __($t.'lab_type') }}</label>
                    <select name="type" id="{{ $id }}-type" class="form-select">
                        @foreach (\App\Enums\LabType::options() as $v => $l)<option value="{{ $v }}" @selected($val('type', $lab?->type?->value ?? 'chemistry') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="{{ $id }}-room">{{ __($t.'room') }}</label>
                    <select name="room_id" id="{{ $id }}-room" class="form-select">
                        <option value="">{{ __('library.none_selected') }}</option>
                        @foreach ($rooms as $rid => $rname)<option value="{{ $rid }}" @selected((int) $val('room_id', $lab?->room_id) === $rid)>{{ $rname }}</option>@endforeach
                    </select>
                    <div class="form-text">{{ __($t.'help.room') }}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="{{ $id }}-staff">{{ __($t.'manager') }}</label>
                    <select name="staff_id" id="{{ $id }}-staff" class="form-select">
                        <option value="">{{ __('library.none_selected') }}</option>
                        @foreach ($staff as $sid => $sname)<option value="{{ $sid }}" @selected((int) $val('staff_id', $lab?->staff_id) === $sid)>{{ $sname }}</option>@endforeach
                    </select>
                </div>
                <x-form.textarea class="col-12" :id="$id.'-safety'" name="safety_notes" :label="__($t.'safety_notes')" :value="$lab?->safety_notes" rows="4" maxlength="2000" :use-old="$open" :placeholder="__($t.'ph.safety_notes')" :help="__($t.'help.safety_notes')" />
                <x-form.input class="col-12" :id="$id.'-notes'" name="notes" :label="__($t.'notes')" :value="$lab?->notes" maxlength="500" :use-old="$open" />
                @if ($lab)
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $id }}-active" @checked($open ? old('is_active') : $lab->is_active)>
                            <label class="form-check-label" for="{{ $id }}-active">{{ __('app.active') }}</label>
                        </div>
                    </div>
                @endif
            </div>
        </x-modal-form>
    @endforeach
@endsection
