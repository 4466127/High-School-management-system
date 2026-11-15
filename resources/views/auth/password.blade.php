@extends('layouts.app', ['title' => __('auth.password_title')])

@section('content')
    <h1 class="page-title">{{ __('auth.password_title') }}</h1>

    <div class="card narrow">
        <div class="card-body">
            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">{{ __('users.current_password') }}</label>
                    <input type="password" id="current_password" name="current_password" dir="ltr" autocomplete="current-password" required
                           class="form-control @error('current_password') is-invalid @enderror">
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('users.new_password') }}</label>
                    <input type="password" id="password" name="password" dir="ltr" autocomplete="new-password" required
                           class="form-control @error('password') is-invalid @enderror">
                    <div class="form-text">{{ __('auth.password_hint') }}</div>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('users.password_confirmation') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" dir="ltr" autocomplete="new-password" required class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}
                </button>
            </form>
        </div>
    </div>
@endsection
