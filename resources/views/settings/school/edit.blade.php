@extends('layouts.app', ['title' => __('school.title')])

@section('content')
    <h1 class="page-title">{{ __('school.title') }}</h1>

    @php($provinces = array_combine(config('afghanistan.provinces'), config('afghanistan.provinces')))

    <form method="POST" action="{{ route('settings.school.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-building-columns" aria-hidden="true"></i> {{ __('school.section_identity') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-6 mb-3" name="school_name" :label="__('school.school_name')" :value="$values['school_name']" required maxlength="150" :placeholder="__('school.school_name_ph')" />
                    <x-form.select class="col-md-3 mb-3" name="school_type" :label="__('school.school_type')" :options="\App\Enums\SchoolType::options()" :value="$values['school_type']" required />
                    <x-form.input class="col-md-3 mb-3" name="principal_name" :label="__('school.principal_name')" :value="$values['principal_name']" maxlength="150" :placeholder="__('school.principal_name_ph')" />
                    <x-form.select class="col-md-3 mb-3" name="province" :label="__('school.province')" :options="$provinces" :value="$values['province']" :placeholder="__('students.ph.province')" />
                    <x-form.input class="col-md-3 mb-3" name="district" :label="__('school.district')" :value="$values['district']" maxlength="100" :placeholder="__('school.district_ph')" />
                    <x-form.input class="col-md-3 mb-3" name="phone" :label="__('school.phone')" :value="$values['phone']" type="tel" ltr maxlength="30" :placeholder="__('school.phone_ph')" />
                    <x-form.input class="col-md-3 mb-3" name="address" :label="__('school.address')" :value="$values['address']" maxlength="300" :placeholder="__('school.address_ph')" />
                    <x-form.input class="col-md-3 mb-3" name="school_code" :label="__('school.school_code')" :value="$values['school_code']" maxlength="50" :help="__('school.school_code_hint')" />
                    <x-form.input class="col-md-3 mb-3" name="email" type="email" ltr :label="__('school.email')" :value="$values['email']" maxlength="150" />
                    <x-form.input class="col-md-6 mb-3" name="header_line" :label="__('school.header_line')" :value="$values['header_line']" maxlength="200" :help="__('school.header_line_hint')" />
                    <x-form.input class="col-md-6 mb-3" name="directorate" :label="__('school.directorate')" :value="$values['directorate']" maxlength="200" :help="__('school.directorate_hint')" />
                    <div class="col-md-6 mb-3">
                        <x-form.input class="mb-1" name="logo" type="file" :label="__('school.logo')" accept="image/jpeg,image/png,image/webp" :help="__('school.logo_hint')" />
                        @if ($logo)
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <img src="{{ route('school.logo') }}" alt="" width="48" height="48" class="rounded object-fit-contain border bg-white">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                    <label class="form-check-label" for="remove_logo">{{ __('school.remove_logo') }}</label>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> {{ __('school.section_letters') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-3 mb-3" name="letter_outgoing_prefix" :label="__('school.letter_outgoing_prefix')" :value="$values['letter_outgoing_prefix']" maxlength="20" />
                    <x-form.input class="col-md-3 mb-3" name="letter_incoming_prefix" :label="__('school.letter_incoming_prefix')" :value="$values['letter_incoming_prefix']" maxlength="20" />
                    <x-form.input class="col-md-3 mb-3" name="letter_signature_1" :label="__('school.letter_signature_1')" :value="$values['letter_signature_1']" maxlength="100" />
                    <x-form.input class="col-md-3 mb-3" name="letter_signature_2" :label="__('school.letter_signature_2')" :value="$values['letter_signature_2']" maxlength="100" />
                </div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="letter_show_stamp" value="1" id="letter_show_stamp" @checked(old('letter_show_stamp', $values['letter_show_stamp'] === '1'))>
                    <label class="form-check-label" for="letter_show_stamp">{{ __('school.letter_show_stamp') }}</label>
                </div>
                <p class="form-text mb-0">{{ __('school.letters_hint') }}</p>
            </div>
        </div>

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-coins" aria-hidden="true"></i> {{ __('school.section_finance') }}</div>
            <div class="card-body">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="fees_enabled" value="1" id="fees_enabled" @checked(old('fees_enabled', $values['fees_enabled'] === '1'))>
                    <label class="form-check-label fw-medium" for="fees_enabled">{{ __('school.fees_enabled') }}</label>
                </div>
                <p class="form-text mt-0 mb-3">{{ __('school.fees_hint') }}</p>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="school_ledger_enabled" value="1" id="school_ledger_enabled" @checked(old('school_ledger_enabled', $values['school_ledger_enabled'] === '1'))>
                    <label class="form-check-label fw-medium" for="school_ledger_enabled">{{ __('school.ledger_enabled') }}</label>
                </div>
                <p class="form-text mt-0 mb-0">{{ __('school.ledger_hint') }}</p>
            </div>
        </div>

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i> {{ __('school.section_grading') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-4 mb-3" name="pass_percent" type="number" step="0.5" min="1" max="100" :label="__('school.pass_percent')" :value="$values['pass_percent']" required :help="__('school.pass_percent_hint')" />
                    <x-form.input class="col-md-4 mb-3" name="conditional_max_failed_subjects" type="number" min="0" max="10" :label="__('school.conditional')" :value="$values['conditional_max_failed_subjects']" required :help="__('school.conditional_hint')" />
                    <x-form.input class="col-md-4 mb-3" name="deprivation_absence_days" type="number" min="0" max="365" :label="__('school.deprivation')" :value="$values['deprivation_absence_days']" required :help="__('school.deprivation_hint')" />
                </div>
                <div class="fw-medium mb-2">{{ __('school.grade_scale') }}</div>
                <div class="row">
                    <x-form.input class="col-md-4 mb-3" name="grade_a_min" type="number" step="0.5" min="1" max="100" :label="__('school.grade_a_min')" :value="$values['grade_a_min']" required />
                    <x-form.input class="col-md-4 mb-3" name="grade_b_min" type="number" step="0.5" min="1" max="100" :label="__('school.grade_b_min')" :value="$values['grade_b_min']" required />
                    <x-form.input class="col-md-4 mb-3" name="grade_c_min" type="number" step="0.5" min="1" max="100" :label="__('school.grade_c_min')" :value="$values['grade_c_min']" required />
                </div>
                <div class="form-text">{{ __('school.grade_scale_hint') }}</div>
            </div>
        </div>

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i> {{ __('school.section_calendar') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-4 mb-3" name="absence_alert_days" type="number" min="1" max="365" :label="__('school.absence_alert')" :value="$values['absence_alert_days']" required :help="__('school.absence_alert_hint')" />
                </div>
                <div class="form-label">{{ __('school.weekly_holidays') }}</div>
                @php($holidays = old('weekly_holidays', $values['weekly_holidays']))
                <div class="d-flex flex-wrap gap-3">
                    @foreach (__('school.weekdays') as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="weekly_holidays[]" value="{{ $key }}" id="wd_{{ $key }}" @checked(in_array($key, (array) $holidays, true))>
                            <label class="form-check-label" for="wd_{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
    </form>
@endsection
