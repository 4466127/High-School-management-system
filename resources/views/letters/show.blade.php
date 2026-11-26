@php($out = $letter->isOutgoing())
@extends('layouts.app', ['title' => $letter->subject])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">
            {{ $letter->subject }}
            <span class="badge {{ $letter->status->badgeClass() }} fs-6 align-middle">{{ $letter->status->label() }}</span>
        </h1>
        <div class="d-flex flex-wrap gap-2">
            @if ($out)
                <a href="{{ route('letters.outgoing.show', [$letter, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('letters.print') }}</a>
                @if ($letter->isDraft())
                    <a href="{{ route('letters.outgoing.edit', $letter) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                    <form method="POST" action="{{ route('letters.outgoing.issue', $letter) }}" data-confirm="{{ __('letters.issue_confirm') }}">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-stamp" aria-hidden="true"></i> {{ __('letters.issue') }}</button>
                    </form>
                @endif
            @else
                @unless ($letter->isCancelled())
                    <a href="{{ route('letters.incoming.edit', $letter) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                    <a href="{{ route('letters.outgoing.create', ['reply_to' => $letter->id]) }}" class="btn btn-primary"><i class="fa-solid fa-reply" aria-hidden="true"></i> {{ __('letters.write_reply') }}</a>
                @endunless
            @endif
            @unless ($letter->isCancelled())
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelLetter">{{ __('letters.cancel') }}</button>
            @endunless
            <a href="{{ route($out ? 'letters.outgoing.index' : 'letters.incoming.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>

    @if ($letter->isCancelled())
        <div class="alert alert-secondary">{{ __('letters.cancelled_info', ['date' => \App\Support\JalaliDate::format($letter->cancelled_at, 'long'), 'user' => $letter->canceller?->name, 'reason' => $letter->cancel_reason]) }}</div>
    @endif
    @if (! $out && $letter->awaitingResponse())
        <div class="alert alert-warning py-2"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> {{ __('letters.awaiting_response') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="letter-body">{{ $letter->body ?: '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('letters.number') }}</dt><dd class="col-7 fw-bold" dir="ltr">{{ $letter->displayNumber() }}</dd>
                        <dt class="col-5">{{ __('letters.date') }}</dt><dd class="col-7">@jdate($letter->letter_date, 'long')</dd>
                        <dt class="col-5">{{ __('letters.type') }}</dt><dd class="col-7">{{ $letter->type->label() }}</dd>
                        @if ($letter->recipient)<dt class="col-5">{{ __($out ? 'letters.recipient' : 'letters.sender') }}</dt><dd class="col-7">{{ $letter->recipient }}</dd>@endif
                        @if ($letter->organization)<dt class="col-5">{{ __('letters.organization') }}</dt><dd class="col-7">{{ $letter->organization }}</dd>@endif
                        @unless ($out)
                            @if ($letter->external_number)<dt class="col-5">{{ __('letters.external_number') }}</dt><dd class="col-7">@fa($letter->external_number)</dd>@endif
                            <dt class="col-5">{{ __('letters.received_on') }}</dt><dd class="col-7">@jdate($letter->received_on, 'long')</dd>
                        @endunless
                        @if ($letter->student)<dt class="col-5">{{ __('letters.student') }}</dt><dd class="col-7"><a href="{{ route('students.show', $letter->student) }}">{{ $letter->student->full_name }}</a></dd>@endif
                        @if ($letter->staff)<dt class="col-5">{{ __('letters.staff') }}</dt><dd class="col-7">{{ $letter->staff->full_name }}</dd>@endif
                        @if ($letter->replyTo)<dt class="col-5">{{ __('letters.reply_to') }}</dt><dd class="col-7"><a href="{{ route('letters.incoming.show', $letter->replyTo) }}" dir="ltr">{{ $letter->replyTo->displayNumber() }}</a></dd>@endif
                        @foreach ($letter->replies as $reply)
                            <dt class="col-5">{{ __('letters.answered_by') }}</dt><dd class="col-7"><a href="{{ route('letters.outgoing.show', $reply) }}">{{ $reply->displayNumber() }}</a> <span class="badge {{ $reply->status->badgeClass() }}">{{ $reply->status->label() }}</span></dd>
                        @endforeach
                    </dl>
                    <div class="text-secondary mt-2">
                        @if ($letter->issued_at)
                            {{ __($out ? 'letters.issued_info' : 'letters.registered_info', ['date' => \App\Support\JalaliDate::format($letter->issued_at, 'long'), 'user' => $letter->issuer?->name]) }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-medium"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> {{ __('letters.attachments') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($letter->attachments as $att)
                        <li class="list-group-item d-flex align-items-center gap-2 small">
                            <a href="{{ route('letters.attachments.show', [$letter, $att]) }}" class="flex-grow-1">{{ $att->title }} <span class="text-secondary">({{ $att->original_name }})</span></a>
                            @if ($letter->isDraft())
                                <form method="POST" action="{{ route('letters.attachments.destroy', [$letter, $att]) }}" data-confirm="{{ __('letters.attachment_remove_confirm') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('app.delete') }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item small text-secondary">{{ __('letters.no_attachments') }}</li>
                    @endforelse
                </ul>
                @unless ($letter->isCancelled())
                    <form method="POST" action="{{ route('letters.attachments.store', $letter) }}" enctype="multipart/form-data" class="card-body border-top" novalidate>
                        @csrf
                        <x-form.input name="title" :label="__('letters.attachment_title')" maxlength="150" required />
                        <x-form.input name="file" type="file" :label="__('letters.attachment_file')" accept=".pdf,image/jpeg,image/png,image/webp" required :help="__('letters.attachment_hint')" />
                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('letters.attachment_add') }}</button>
                    </form>
                @endunless
            </div>
        </div>
    </div>

    @unless ($letter->isCancelled())
        <x-modal-form id="cancelLetter" :title="__('letters.cancel')" :action="route('letters.cancel', $letter)" :submit="__('letters.cancel')" submit-class="btn-danger">
            <x-form.textarea name="reason" :label="__('letters.cancel_reason')" required rows="3" :use-old="old('_modal') === 'cancelLetter'" />
        </x-modal-form>
    @endunless
@endsection
