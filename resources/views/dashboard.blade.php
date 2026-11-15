@extends('layouts.app', ['title' => __('menu.dashboard')])

@section('content')
    <div class="welcome">
        <div class="welcome-date">@jdate(now(), 'full')</div>
        <div class="fw-bold fs-4 my-1">{{ __('app.dashboard.welcome', ['name' => $user->name]) }}</div>
        <div class="small">
            {{ __('app.dashboard.your_roles') }}:
            @forelse ($user->roles as $role)
                <span class="badge">{{ $role->label() }}</span>
            @empty
                {{ __('app.none') }}
            @endforelse
        </div>
    </div>

    <h2 class="section-title">{{ __('app.dashboard.quick_access') }}</h2>
    <div class="hk-tiles">
        @foreach ($tiles as $tile)
            @if ($tile['url'])
                <a href="{{ $tile['url'] }}" class="hk-tile">
                    <span class="hk-tile-icon"><i class="fa-solid {{ $tile['icon'] }}" aria-hidden="true"></i></span>
                    {{ $tile['label'] }}
                </a>
            @else
                <div class="hk-tile is-soon" aria-disabled="true">
                    <span class="hk-tile-icon"><i class="fa-solid {{ $tile['icon'] }}" aria-hidden="true"></i></span>
                    {{ $tile['label'] }}
                    <small class="text-secondary">{{ __('app.soon') }}</small>
                </div>
            @endif
        @endforeach
    </div>

    <p class="text-secondary small mt-3">{{ __('app.dashboard.phase_note') }}</p>
@endsection
