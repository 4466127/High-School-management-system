@extends('layouts.app', ['title' => __('menu.dashboard')])

@section('content')
    <div class="welcome">
        <div class="fw-bold fs-5">{{ __('app.dashboard.welcome', ['name' => $user->name]) }}</div>
        <div class="small mt-1">
            {{ __('app.dashboard.your_roles') }}:
            @forelse ($user->roles as $role)
                <span class="badge text-bg-light border">{{ $role->label() }}</span>
            @empty
                {{ __('app.none') }}
            @endforelse
        </div>
    </div>

    <h2 class="h6 fw-bold mb-2">{{ __('app.dashboard.quick_access') }}</h2>
    <div class="hk-tiles">
        @foreach ($tiles as $tile)
            @if ($tile['url'])
                <a href="{{ $tile['url'] }}" class="hk-tile">
                    <i class="fa-solid {{ $tile['icon'] }} hk-tile-icon" aria-hidden="true"></i>
                    {{ $tile['label'] }}
                </a>
            @else
                <div class="hk-tile opacity-50" aria-disabled="true">
                    <i class="fa-solid {{ $tile['icon'] }} hk-tile-icon" aria-hidden="true"></i>
                    {{ $tile['label'] }}
                    <small class="text-secondary">{{ __('app.soon') }}</small>
                </div>
            @endif
        @endforeach
    </div>

    <p class="text-secondary small">{{ __('app.dashboard.phase_note') }}</p>
@endsection
