@extends('layouts.app', ['title' => __('letters.templates_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('letters.templates_title') }}</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('letters.template_create') }}</button>
    </div>
    <div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ __('letters.templates_hint') }}</div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <ul class="list-group list-group-flush">
                    @forelse ($templates as $t)
                        <li class="list-group-item">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span @class(['fw-medium flex-grow-1', 'text-secondary' => ! $t->is_active])>{{ $t->name }}</span>
                                <span class="badge text-bg-light border">{{ $t->type->label() }}</span>
                                @unless ($t->is_active)<span class="badge text-bg-secondary">{{ __('letters.template_inactive') }}</span>@endunless
                                @if ($t->is_active)
                                    <a href="{{ route('letters.outgoing.create', ['template' => $t->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('letters.create_outgoing') }}</a>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#template{{ $t->id }}">{{ __('app.edit') }}</button>
                            </div>
                            <div class="small text-secondary text-truncate">{{ \Illuminate\Support\Str::limit($t->body, 140) }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">{{ __('letters.none') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header fw-medium">{{ __('letters.placeholders_title') }}</div>
                <div class="card-body small">
                    <p class="text-secondary">{{ __('letters.placeholders_hint') }}</p>
                    <dl class="row mb-0">
                        @foreach ($placeholders as $p)
                            <dt class="col-6 fw-normal user-select-all" dir="ltr">{{ '{'.$p.'}' }}</dt>
                            <dd class="col-6 mb-1">{{ __('letters.placeholders.'.$p) }}</dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @foreach ([null, ...$templates] as $t)
        @php($id = $t ? 'template'.$t->id : 'templateCreate')
        @php($useOld = old('_modal') === $id)
        <x-modal-form :id="$id" :title="$t ? __('letters.template_edit') : __('letters.template_create')" :action="$t ? route('letters.templates.update', $t) : route('letters.templates.store')" :method="$t ? 'PUT' : 'POST'" :submit="__('app.save')" size="lg">
            <x-form.input :id="$id.'-name'" name="name" :label="__('letters.template_name')" :value="$t?->name" required maxlength="150" :use-old="$useOld" />
            <div class="mb-3">
                <label class="form-label" for="{{ $id }}-type">{{ __('letters.type') }}</label>
                <select name="type" id="{{ $id }}-type" class="form-select">
                    @foreach (\App\Enums\LetterType::options() as $v => $l)<option value="{{ $v }}" @selected(($useOld ? old('type') : $t?->type?->value) === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <x-form.input :id="$id.'-subject'" name="subject" :label="__('letters.subject')" :value="$t?->subject" maxlength="255" :use-old="$useOld" />
            <x-form.textarea :id="$id.'-body'" name="body" :label="__('letters.body')" :value="$t?->body" rows="10" required :use-old="$useOld" />
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $id }}-active" @checked($useOld ? old('is_active') : ($t?->is_active ?? true))>
                <label class="form-check-label" for="{{ $id }}-active">{{ __('letters.template_active') }}</label>
            </div>
        </x-modal-form>
    @endforeach
@endsection
