@extends('layouts.app', ['title' => __('letters.create_outgoing')])

@section('content')
    <h1 class="page-title">{{ __('letters.create_outgoing') }}</h1>

    @if ($replyTo)
        <div class="alert alert-info py-2">{{ __('letters.reply_to') }}: <a href="{{ route('letters.incoming.show', $replyTo) }}">{{ $replyTo->displayNumber() }} — {{ $replyTo->subject }}</a></div>
    @endif

    {{-- قدم ۱: قالب و شخص (GET؛ متن را از نو پر می‌کند) --}}
    <form method="GET" action="{{ route('letters.outgoing.create') }}" class="card card-body mb-3">
        <div class="small text-secondary mb-2">{{ __('letters.template_step') }}</div>
        @if ($replyTo)<input type="hidden" name="reply_to" value="{{ $replyTo->id }}">@endif
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="t-template" class="form-label">{{ __('letters.template') }}</label>
                <select name="template" id="t-template" class="form-select">
                    <option value="">{{ __('letters.no_template') }}</option>
                    @foreach ($templates as $t)<option value="{{ $t->id }}" @selected($template?->id === $t->id)>{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="t-student" class="form-label">{{ __('letters.student_admission') }}</label>
                <input type="text" name="student" id="t-student" value="{{ $studentQuery }}" class="form-control" dir="ltr" autocomplete="off">
                @if ($studentQuery !== '' && ! $student)<div class="small text-danger mt-1">{{ __('letters.student_not_found') }}</div>@endif
                @if ($student)<div class="small text-success mt-1">{{ $student->full_name }}</div>@endif
            </div>
            <div class="col-md-3">
                <label for="t-staff" class="form-label">{{ __('letters.staff') }}</label>
                <select name="staff" id="t-staff" class="form-select">
                    <option value="">—</option>
                    @foreach ($staffList as $s)<option value="{{ $s->id }}" @selected($staff?->id === $s->id)>{{ $s->full_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('letters.fill_from_template') }}</button></div>
        </div>
    </form>

    {{-- قدم ۲: متن و ذخیره --}}
    <form method="POST" action="{{ route('letters.outgoing.store') }}" class="card form-section" novalidate>
        @csrf
        <div class="card-body">
            <div class="small text-secondary mb-2">{{ __('letters.letter_step') }}</div>
            @include('letters._form')
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('letters.save_draft') }}</button>
            <a href="{{ route('letters.outgoing.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
