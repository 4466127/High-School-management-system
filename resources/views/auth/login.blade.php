@extends('layouts.auth', ['title' => __('auth.login_title')])

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="username" class="form-label">{{ __('users.username') }}</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}"
                   class="form-control @error('username') is-invalid @enderror"
                   dir="ltr" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
            @error('username')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('users.password') }}</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   dir="ltr" autocomplete="current-password" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
            <label class="form-check-label" for="remember">{{ __('auth.remember') }}</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> {{ __('auth.login') }}
        </button>

        <p class="text-secondary small text-center mt-3 mb-0">{{ __('auth.forgot_hint') }}</p>
    </form>
@endsection
