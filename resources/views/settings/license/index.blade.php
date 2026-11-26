@extends('layouts.app', ['title' => __('license.title')])

@php
    $state = $status['state'];
    $license = $status['license'];
    $tone = match ($state) {
        'valid' => 'success',
        'expiring', 'missing', 'not_configured' => 'warning',
        default => 'danger',
    };
    $date = fn ($v) => $v ? \App\Support\JalaliDate::format(\Carbon\CarbonImmutable::parse($v), 'long') : __('license.permanent');
@endphp

@section('content')
    <h1 class="page-title">{{ __('license.title') }}</h1>
    <p class="text-secondary">{{ __('license.intro') }}</p>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card card-body h-100 border-{{ $tone }}">
                <div class="small text-secondary">{{ __('license.state') }}</div>
                <div class="fs-5 fw-bold text-{{ $tone }}-emphasis">
                    {{ $state === 'not_configured' ? __('license.not_configured') : __('license.states.'.$state) }}
                    @if ($status['days_left'] !== null && $status['days_left'] >= 0)
                        <span class="fs-6 fw-normal">({{ __('license.days_left', ['days' => \App\Support\Digits::toPersian((string) $status['days_left'])]) }})</span>
                    @endif
                </div>
                @if ($license)
                    <dl class="row small mb-0 mt-2">
                        <dt class="col-5">{{ __('license.license_id') }}</dt><dd class="col-7" dir="ltr">{{ $license['license_id'] }}</dd>
                        <dt class="col-5">{{ __('license.school') }}</dt><dd class="col-7">{{ $license['school_name'] }}</dd>
                        @if (($license['edition'] ?? '') !== '')<dt class="col-5">{{ __('license.edition') }}</dt><dd class="col-7">{{ $license['edition'] }}</dd>@endif
                        <dt class="col-5">{{ __('license.issued_on') }}</dt><dd class="col-7">{{ $date($license['issued_on'] ?? null) }}</dd>
                        <dt class="col-5">{{ __('license.expires_on') }}</dt><dd class="col-7 mb-0">{{ $date($license['expires_on'] ?? null) }}</dd>
                    </dl>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-body h-100">
                <div class="small text-secondary">{{ __('license.installation_id') }}</div>
                <div class="fw-bold user-select-all" dir="ltr">{{ $installationId }}</div>
                <div class="form-text">{{ __('license.installation_hint') }}</div>
            </div>
        </div>
    </div>

    @if ($configured)
        <form method="POST" action="{{ route('settings.license.store') }}" enctype="multipart/form-data" class="card form-section" novalidate>
            @csrf
            <div class="card-header"><i class="fa-solid fa-key" aria-hidden="true"></i> {{ __('license.install') }}</div>
            <div class="card-body">
                <x-form.input name="license" type="file" :label="__('license.file')" accept=".json,application/json" required />
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload" aria-hidden="true"></i> {{ __('license.install') }}</button>
            </div>
        </form>
    @endif
@endsection
