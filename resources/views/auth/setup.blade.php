@extends('layouts.auth', ['title' => __('setup.title'), 'wide' => true])

@section('content')
    <h2 class="h6 fw-bold">{{ __('setup.title') }}</h2>
    <p class="text-secondary small">{{ __('setup.intro') }}</p>

    @php($provinces = array_combine(config('afghanistan.provinces'), config('afghanistan.provinces')))

    <form method="POST" action="{{ route('setup.store') }}" novalidate>
        @csrf

        <x-form.input class="mb-3" name="token" :label="__('setup.token')" required ltr maxlength="20" autocomplete="off" :help="__('setup.token_hint')" />

        <fieldset class="mb-2">
            <legend class="fs-6 fw-bold">{{ __('school.section_identity') }}</legend>
            <div class="row">
                <x-form.input class="col-md-8 mb-3" name="school_name" :label="__('school.school_name')" required maxlength="150" :placeholder="__('school.school_name_ph')" />
                <x-form.select class="col-md-4 mb-3" name="school_type" :label="__('school.school_type')" :options="\App\Enums\SchoolType::options()" value="government" required />
                <x-form.select class="col-md-6 mb-3" name="province" :label="__('school.province')" :options="$provinces" :placeholder="__('students.ph.province')" />
                <x-form.input class="col-md-6 mb-3" name="district" :label="__('school.district')" maxlength="100" :placeholder="__('school.district_ph')" />
                <x-form.input class="col-md-6 mb-3" name="address" :label="__('school.address')" maxlength="300" />
                <x-form.input class="col-md-6 mb-3" name="phone" type="tel" ltr :label="__('school.phone')" maxlength="30" />
                <x-form.input class="col-12 mb-3" name="principal_name" :label="__('school.principal_name')" maxlength="150" />
            </div>
        </fieldset>

        <fieldset class="mb-2">
            <legend class="fs-6 fw-bold">{{ __('setup.admin_section') }}</legend>
            <p class="form-text mt-0">{{ __('setup.admin_hint') }}</p>
            <div class="row">
                <x-form.input class="col-md-6 mb-3" name="name" :label="__('setup.admin_name')" required maxlength="150" />
                <x-form.input class="col-md-6 mb-3" name="username" :label="__('users.username')" required ltr maxlength="50" autocomplete="username" :help="__('users.username_format')" />
                <x-form.input class="col-md-6 mb-3" name="password" type="password" :label="__('users.password')" required ltr autocomplete="new-password" :help="__('setup.password_hint')" />
                <x-form.input class="col-md-6 mb-3" name="password_confirmation" type="password" :label="__('setup.password_confirmation')" required ltr autocomplete="new-password" />
            </div>
        </fieldset>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('setup.submit') }}
        </button>
    </form>
@endsection
