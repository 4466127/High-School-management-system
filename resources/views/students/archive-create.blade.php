@extends('layouts.app', ['title' => __('past_results.former_title')])

@php
    $p = 'past_results.';
    $provinces = array_combine(config('afghanistan.provinces'), config('afghanistan.provinces'));
    $statuses = collect(\App\Http\Requests\Students\FormerStudentRequest::STATUSES)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __($p.'former_title') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('import.index', ['type' => 'graduates']) }}" class="btn btn-outline-success"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> {{ __($p.'former_import') }}</a>
            <a href="{{ route('student-archive.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($p.'former_intro') }}</div>

    <form method="POST" action="{{ route('student-archive.store') }}" novalidate>
        @csrf
        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-user" aria-hidden="true"></i> {{ __($p.'former_person') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-3 mb-3" name="first_name" :label="__('students.first_name')" required maxlength="100" :placeholder="__('students.ph.first_name')" />
                    <x-form.input class="col-md-3 mb-3" name="last_name" :label="__('students.last_name')" maxlength="100" :placeholder="__('students.ph.last_name')" />
                    <x-form.input class="col-md-3 mb-3" name="father_name" :label="__('students.father_name')" required maxlength="100" :placeholder="__('students.ph.father_name')" />
                    <x-form.input class="col-md-3 mb-3" name="grandfather_name" :label="__('students.grandfather_name')" maxlength="100" :placeholder="__('students.ph.grandfather_name')" />
                    <x-form.select class="col-md-3 mb-3" name="gender" :label="__('students.gender')" :options="\App\Enums\Gender::studentOptions()" required :placeholder="__('students.ph.gender')" />
                    <x-jalali-date class="col-md-3 mb-3" name="birth_date" :label="__('students.birth_date')" />
                    <x-form.input class="col-md-3 mb-3" name="tazkira_no" :label="__('students.tazkira_no')" maxlength="50" ltr :placeholder="__('students.ph.tazkira_no')" />
                    <x-form.input class="col-md-3 mb-3" name="admission_no" :label="__('students.admission_no')" maxlength="30" ltr :placeholder="__($p.'ph.admission_no')" :help="__($p.'admission_no_hint')" />
                    <x-form.input class="col-md-3 mb-3" name="phone" type="tel" :label="__('students.phone')" maxlength="30" ltr :placeholder="__('students.ph.phone')" />
                    <x-form.select class="col-md-3 mb-3" name="province" :label="__('students.province')" :options="$provinces" :placeholder="__('students.ph.province')" />
                    <x-form.input class="col-md-6 mb-3" name="notes" :label="__('students.notes')" maxlength="2000" :placeholder="__('students.ph.notes')" />
                </div>
            </div>
        </div>

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-award" aria-hidden="true"></i> {{ __($p.'former_leaving') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-form.select class="col-md-3 mb-3" name="status" :label="__('students.status')" :options="$statuses" value="graduated" required />
                    <x-jalali-date class="col-md-3 mb-3" name="left_on" :label="__($p.'left_on')" :help="__($p.'left_on_hint')" />
                    <x-form.input class="col-md-6 mb-3" name="reason" :label="__('students.reason')" maxlength="500" :placeholder="__($p.'ph.reason')" />
                    <x-form.input class="col-md-3 mb-3" name="certificate_no" :label="__('archive.certificate_no')" maxlength="100" :placeholder="__($p.'ph.certificate_no')" :help="__($p.'certificate_hint')" />
                    <x-jalali-date class="col-md-3 mb-3" name="certificate_date" :label="__('archive.certificate_date')" />
                    <x-form.input class="col-md-6 mb-3" name="certificate_note" :label="__('archive.certificate_note')" maxlength="500" :placeholder="__($p.'ph.certificate_note')" />
                </div>
            </div>
        </div>

        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-scroll" aria-hidden="true"></i> {{ __($p.'former_results') }}</div>
            <div class="card-body">
                <p class="form-text mt-0">{{ __($p.'former_results_hint') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('academics.grade') }}</th><th style="width: 120px">{{ __($p.'year_name') }}</th><th style="width: 120px">{{ __('exams.average') }}</th>
                            <th style="width: 170px">{{ __('exams.result') }}</th><th>{{ __($p.'school_name') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($levels as $i => $level)
                            <tr>
                                <td class="fw-medium">{{ $level->name }}<input type="hidden" name="results[{{ $i }}][grade_level_id]" value="{{ $level->id }}"></td>
                                @foreach (['year_name' => __($p.'year_name_ph'), 'average' => __($p.'average_ph')] as $field => $ph)
                                    <td>
                                        <input type="text" name="results[{{ $i }}][{{ $field }}]" value="{{ old("results.$i.$field") }}" inputmode="numeric" class="form-control form-control-sm ltr @error("results.$i.$field") is-invalid @enderror" placeholder="{{ $ph }}" aria-label="{{ $level->name }} — {{ $field }}">
                                        @error("results.$i.$field")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                @endforeach
                                <td>
                                    <select name="results[{{ $i }}][result]" class="form-select form-select-sm" aria-label="{{ __('exams.result') }}">
                                        <option value="">{{ __($p.'auto') }}</option>
                                        @foreach (\App\Enums\FinalResult::options() as $v => $l)<option value="{{ $v }}" @selected(old("results.$i.result") === $v)>{{ $l }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="results[{{ $i }}][school_name]" value="{{ old("results.$i.school_name") }}" maxlength="200" class="form-control form-control-sm" placeholder="{{ __($p.'school_name_ph') }}" aria-label="{{ __($p.'school_name') }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __($p.'former_save') }}</button>
            <button type="submit" name="add_another" value="1" class="btn btn-outline-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($p.'former_save_next') }}</button>
        </div>
    </form>
@endsection
