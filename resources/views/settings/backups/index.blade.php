@extends('layouts.app', ['title' => __('backup.title')])

@php
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $latest = $backups->first();
    $now = \Carbon\CarbonImmutable::now(\App\Support\JalaliDate::TIMEZONE);
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="page-title mb-0">{{ __('backup.title') }}</h1>
        <form method="POST" action="{{ route('settings.backups.store') }}">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('backup.create') }}</button>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div @class(['card card-body h-100', 'border-warning' => $due])>
                <div class="small text-secondary">{{ __('backup.last') }}</div>
                @if ($latest)
                    <div class="fs-5 fw-bold">@jdate($latest['time'], 'long') · {{ \App\Support\Clock::withPeriod($latest['time']) }}</div>
                    <div class="small text-secondary">{{ __('backup.days_ago', ['days' => $n((int) $latest['time']->diffInDays($now))]) }}</div>
                @else
                    <div class="fs-5 fw-bold">{{ __('backup.never') }}</div>
                @endif
                @if ($due)
                    <div class="small text-warning-emphasis mt-1"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ __('backup.due') }}</div>
                @endif
            </div>
        </div>
        <div class="col-md-8">
            <div class="card card-body h-100 small">
                <p class="mb-1">{{ __('backup.contains') }}</p>
                <p class="mb-1">{{ __('backup.auto_hint', ['days' => $n(config('school.backup.every_days')), 'keep' => $n(config('school.backup.keep'))]) }}</p>
                <p class="mb-1 fw-medium">{{ __('backup.usb_hint') }}</p>
                <div class="text-secondary">{{ __('backup.folder') }}: <span dir="ltr">{{ $directory }}</span></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-medium">{{ __('backup.list') }} ({{ $n($backups->count()) }})</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('backup.time') }}</th><th>{{ __('backup.reason') }}</th><th>{{ __('backup.size') }}</th><th class="d-none d-md-table-cell">{{ __('backup.name') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($backups as $b)
                    <tr>
                        <td>@jdate($b['time'], 'long') · {{ \App\Support\Clock::withPeriod($b['time']) }}</td>
                        <td><span class="badge text-bg-light border">{{ __('backup.reasons.'.$b['reason']) }}</span></td>
                        <td>{{ $n(number_format($b['size'] / 1024)) }} KB</td>
                        <td class="d-none d-md-table-cell small text-secondary" dir="ltr">{{ $b['name'] }}</td>
                        <td class="text-end"><a href="{{ route('settings.backups.download', $b['name']) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download" aria-hidden="true"></i> {{ __('backup.download') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('backup.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-body small">
        <div class="fw-medium mb-1"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> {{ __('backup.restore_title') }}</div>
        {{ __('backup.restore_hint') }}
    </div>
@endsection
