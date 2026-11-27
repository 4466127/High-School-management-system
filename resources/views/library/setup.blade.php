@extends('layouts.app', ['title' => __('library.setup_title')])

@php($t = 'library.')

@section('content')
    <h1 class="page-title">{{ __($t.'setup_title') }}</h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'setup_intro') }}</div>

    <form method="POST" action="{{ route('library.setup.update') }}" class="card" novalidate>
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-6" name="library_name" :label="__($t.'setup_fields.library_name')" :value="$name" maxlength="150" :placeholder="__($t.'default_name', ['school' => (string) \App\Models\Setting::get('school_name')])" :help="__($t.'help.library_name')" />
                <div class="w-100"></div>
                <x-form.input class="col-6 col-md-3" name="library_loan_days_student" type="number" min="1" max="365" :label="__($t.'setup_fields.library_loan_days_student')" :value="$rules['days_student']" required :help="__($t.'help.days')" />
                <x-form.input class="col-6 col-md-3" name="library_loan_days_staff" type="number" min="1" max="365" :label="__($t.'setup_fields.library_loan_days_staff')" :value="$rules['days_staff']" required />
                <x-form.input class="col-6 col-md-3" name="library_max_student" type="number" min="1" max="20" :label="__($t.'setup_fields.library_max_student')" :value="$rules['max_student']" required :help="__($t.'help.max_loans')" />
                <x-form.input class="col-6 col-md-3" name="library_max_staff" type="number" min="1" max="50" :label="__($t.'setup_fields.library_max_staff')" :value="$rules['max_staff']" required />
                <x-form.input class="col-6 col-md-3" name="library_max_renewals" type="number" min="0" max="10" :label="__($t.'setup_fields.library_max_renewals')" :value="$rules['max_renewals']" required :help="__($t.'help.renewals')" />
                <div class="col-md-9 mb-3 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="library_block_overdue" value="1" id="block-overdue" @checked(old('library_block_overdue', $rules['block_overdue']))>
                        <label class="form-check-label" for="block-overdue">{{ __($t.'setup_fields.library_block_overdue') }}</label>
                        <div class="form-text">{{ __($t.'help.block_overdue') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection
