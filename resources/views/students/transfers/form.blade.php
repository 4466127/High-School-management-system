@extends('layouts.app', ['title' => __($incoming ? 'transfers.in_title' : 'transfers.out_title')])

@php($provinces = array_combine(config('afghanistan.provinces'), config('afghanistan.provinces')))

@section('content')
    <h1 class="page-title">{{ __($incoming ? 'transfers.in_title' : 'transfers.out_title') }} — {{ $student->full_name }}</h1>

    <div class="card card-body mb-3 small">
        <div><span class="text-secondary">{{ __('students.father_name') }}:</span> {{ $student->father_name }} · <span class="text-secondary">{{ __('students.admission_no') }}:</span> @fa($student->admission_no) · <span class="text-secondary">{{ __('students.status') }}:</span> {{ $student->status->label() }}</div>
        @unless ($incoming)
            <div class="mt-1"><span class="text-secondary">{{ __('transfers.current_class') }}:</span> {{ $enrollment ? $enrollment->section->label : __('transfers.not_enrolled') }}</div>
            <div class="mt-1 text-warning-emphasis"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('transfers.out_effect') }}</div>
        @endunless
    </div>

    <form method="POST" action="{{ route($incoming ? 'student-transfers.in.store' : 'student-transfers.out.store', $student) }}" enctype="multipart/form-data" class="card form-section" novalidate>
        @csrf
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-6 mb-3" name="other_school" :label="__($incoming ? 'transfers.other_school_in' : 'transfers.other_school_out')" required maxlength="200" />
                <x-form.select class="col-md-3 mb-3" name="other_province" :label="__('transfers.other_province')" :options="$provinces" :placeholder="'—'" />
                <x-form.input class="col-md-3 mb-3" name="other_district" :label="__('transfers.other_district')" maxlength="100" />
                <div class="col-md-4 mb-3"><x-jalali-date name="transfer_date" :label="__('transfers.transfer_date')" :value="now()" required /></div>
                <div class="col-md-4 mb-3"><x-jalali-date name="requested_on" :label="__('transfers.requested_on')" /></div>
                <div class="col-md-4 mb-3"><x-jalali-date name="approved_on" :label="__('transfers.approved_on')" /></div>
                <x-form.input class="col-md-6 mb-3" name="reference_number" :label="__('transfers.reference_number')" maxlength="100" :help="$incoming ? __('transfers.reference_hint_in') : null" />
                @if ($incoming)
                    <x-form.input class="col-md-6 mb-3" name="last_grade" :label="__('transfers.last_grade')" maxlength="100" />
                @endif
            </div>
            <x-form.textarea name="reason" :label="__('transfers.reason')" required rows="2" maxlength="500" />

            @unless ($incoming)
                <div class="card card-body mb-3 bg-light">
                    <div class="fw-medium mb-1"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> {{ __('transfers.clearance') }}</div>
                    @if (! \App\Models\Setting::enabled('fees_enabled'))
                        <div class="small text-secondary">{{ __('transfers.clearance_off') }}</div>
                    @elseif ($outstanding > 0)
                        <div class="text-danger fw-medium mb-2">{{ __('transfers.outstanding', ['amount' => \App\Services\StudentRecordService::money($outstanding)]) }}</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input @error('clearance_confirmed') is-invalid @enderror" type="checkbox" name="clearance_confirmed" value="1" id="clearance_confirmed" @checked(old('clearance_confirmed'))>
                            <label class="form-check-label" for="clearance_confirmed">{{ __('transfers.clearance_confirmed') }}</label>
                            @error('clearance_confirmed')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div class="small text-success">{{ __('transfers.clearance_ok') }}</div>
                    @endif
                    @if (($libraryLoans ?? collect())->isNotEmpty())
                        <div class="small text-danger mt-2"><i class="fa-solid fa-book" aria-hidden="true"></i> {{ __('library.transfer_warning', ['count' => \App\Support\Digits::toPersian((string) $libraryLoans->count()), 'titles' => $libraryLoans->map(fn ($l) => $l->copy->book->title)->implode('، ')]) }}</div>
                    @endif
                    <x-form.input class="mb-0 mt-2" name="clearance_note" :label="__('transfers.clearance_note')" maxlength="500" />
                </div>
            @endunless

            <x-form.textarea name="notes" :label="__('transfers.notes')" rows="2" maxlength="2000" />

            @if ($incoming)
                <x-form.input name="package" type="file" :label="__('transfers.package')" accept=".json,application/json" :help="__('transfers.package_hint')" />
            @endif

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            <a href="{{ route('students.show', $student) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
