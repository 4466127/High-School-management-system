@extends('layouts.app', ['title' => $letter->exists ? __('letters.edit_incoming') : __('letters.create_incoming')])

@section('content')
    <h1 class="page-title">{{ $letter->exists ? __('letters.edit_incoming') : __('letters.create_incoming') }}</h1>

    <form method="POST" action="{{ $letter->exists ? route('letters.incoming.update', $letter) : route('letters.incoming.store') }}" enctype="multipart/form-data" class="card form-section" novalidate>
        @csrf
        @if ($letter->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-6 mb-3" name="organization" :label="__('letters.organization')" :value="$letter->organization" required maxlength="200" />
                <x-form.input class="col-md-6 mb-3" name="recipient" :label="__('letters.sender')" :value="$letter->recipient" maxlength="200" />
                <x-form.input class="col-md-4 mb-3" name="external_number" :label="__('letters.external_number')" :value="$letter->external_number" maxlength="100" />
                <div class="col-md-4 mb-3"><x-jalali-date name="letter_date" :label="__('letters.letter_date')" :value="$letter->letter_date" required /></div>
                <div class="col-md-4 mb-3"><x-jalali-date name="received_on" :label="__('letters.received_on')" :value="$letter->received_on" required /></div>
                <x-form.select class="col-md-4 mb-3" name="type" :label="__('letters.type')" :options="\App\Enums\LetterType::options()" :value="$letter->type?->value" required />
                <x-form.select class="col-md-4 mb-3" name="staff_id" :label="__('letters.staff')" :options="$staffList->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" :value="$letter->staff_id" :placeholder="'—'" />
                <input type="hidden" name="student_id" value="{{ old('student_id', $letter->student_id) }}">
            </div>
            <x-form.input name="subject" :label="__('letters.subject')" :value="$letter->subject" required maxlength="255" />
            <x-form.textarea name="body" :label="__('letters.body')" :value="$letter->body" rows="6" />
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="needs_response" value="1" id="needs_response" @checked(old('needs_response', $letter->needs_response))>
                <label class="form-check-label" for="needs_response">{{ __('letters.needs_response') }}</label>
            </div>
            @unless ($letter->exists)
                <x-form.input name="scan" type="file" :label="__('letters.scan')" accept=".pdf,image/jpeg,image/png,image/webp" :help="__('letters.scan_hint')" />
            @endunless
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            <a href="{{ $letter->exists ? route('letters.incoming.show', $letter) : route('letters.incoming.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
