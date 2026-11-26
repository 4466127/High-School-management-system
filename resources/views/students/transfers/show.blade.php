@extends('layouts.app', ['title' => __('transfers.show_title', ['name' => $transfer->student->full_name])])

@php($student = $transfer->student)

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">
            {{ __('transfers.show_title', ['name' => $student->full_name]) }}
            <span class="badge {{ $transfer->direction->badgeClass() }} fs-6 align-middle">{{ $transfer->direction->label() }}</span>
            @if ($transfer->isCancelled())<span class="badge text-bg-light border fs-6 align-middle">{{ __('transfers.cancelled') }}</span>@endif
        </h1>
        <div class="d-flex flex-wrap gap-2">
            @if ($transfer->isOut() && ! $transfer->isCancelled())
                <a href="{{ route('student-transfers.record', [$student, 'transfer' => $transfer->id]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('transfers.certificate') }}</a>
                @can('letters.manage')
                    <a href="{{ route('letters.outgoing.create', ['template' => $letterTemplate?->id, 'student' => $student->admission_no, 'transfer' => $transfer->id]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> {{ __('transfers.letter') }}</a>
                @endcan
                <a href="{{ route('student-transfers.package', $student) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-export" aria-hidden="true"></i> {{ __('transfers.record_file') }}</a>
            @endif
            @unless ($transfer->isCancelled())
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelTransfer">{{ __('transfers.cancel') }}</button>
            @endunless
            <a href="{{ route('students.show', $student) }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>

    @if ($transfer->isCancelled())
        <div class="alert alert-secondary">{{ __('transfers.cancelled_info', ['date' => \App\Support\JalaliDate::format($transfer->cancelled_at, 'long'), 'user' => $transfer->canceller?->name, 'reason' => $transfer->cancel_reason]) }}</div>
    @endif
    @if ($transfer->isOut() && ! $transfer->isCancelled())
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('transfers.record_file_hint') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __($transfer->isOut() ? 'transfers.other_school_out' : 'transfers.other_school_in') }}</dt>
                <dd class="col-sm-9">{{ collect([$transfer->other_school, $transfer->other_district, $transfer->other_province])->filter()->join('، ') }}</dd>
                <dt class="col-sm-3">{{ __('transfers.transfer_date') }}</dt><dd class="col-sm-9">@jdate($transfer->transfer_date, 'long')</dd>
                @if ($transfer->requested_on)<dt class="col-sm-3">{{ __('transfers.requested_on') }}</dt><dd class="col-sm-9">@jdate($transfer->requested_on, 'long')</dd>@endif
                @if ($transfer->approved_on)<dt class="col-sm-3">{{ __('transfers.approved_on') }}</dt><dd class="col-sm-9">@jdate($transfer->approved_on, 'long')</dd>@endif
                @if ($transfer->reference_number)<dt class="col-sm-3">{{ __('transfers.reference_number') }}</dt><dd class="col-sm-9">@fa($transfer->reference_number)</dd>@endif
                <dt class="col-sm-3">{{ __('transfers.reason') }}</dt><dd class="col-sm-9">{{ $transfer->reason }}</dd>
                @if ($transfer->enrollment)<dt class="col-sm-3">{{ __('transfers.current_class') }}</dt><dd class="col-sm-9">{{ $transfer->enrollment->section->label }}</dd>@endif
                @if ($transfer->last_grade)<dt class="col-sm-3">{{ __('transfers.last_grade') }}</dt><dd class="col-sm-9">{{ $transfer->last_grade }}</dd>@endif
                @if ($transfer->isOut())
                    <dt class="col-sm-3">{{ __('transfers.clearance') }}</dt>
                    <dd class="col-sm-9">
                        {{ (float) $transfer->outstanding_balance > 0 ? __('transfers.outstanding', ['amount' => \App\Support\Money::format($transfer->outstanding_balance)]) : __('transfers.clearance_ok') }}
                        @if ($transfer->clearance_note)<div class="small text-secondary">{{ $transfer->clearance_note }}</div>@endif
                    </dd>
                @endif
                @if ($transfer->notes)<dt class="col-sm-3">{{ __('transfers.notes') }}</dt><dd class="col-sm-9 letter-body">{{ $transfer->notes }}</dd>@endif
                <dt class="col-sm-3">{{ __('app.created_by') }}</dt><dd class="col-sm-9 mb-0">{{ $transfer->creator?->name }} — @jdate($transfer->created_at)</dd>
            </dl>
        </div>
    </div>

    @if ($transfer->previous_records)
        @include('students.partials.previous-records', ['records' => $transfer->previous_records, 'student' => $student])
    @endif

    @unless ($transfer->isCancelled())
        <x-modal-form id="cancelTransfer" :title="__('transfers.cancel')" :action="route('student-transfers.cancel', $transfer)" :submit="__('transfers.cancel')" submit-class="btn-danger">
            <p class="small text-secondary">{{ __('transfers.cancel_hint') }}</p>
            <x-form.textarea name="reason" :label="__('transfers.cancel_reason')" required rows="3" :use-old="old('_modal') === 'cancelTransfer'" />
        </x-modal-form>
    @endunless
@endsection
