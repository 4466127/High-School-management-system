@extends('layouts.app', ['title' => __('users.edit')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('users.edit') }}: {{ $user->name }}</h1>
        <a href="{{ route('settings.users.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.users.update', $user) }}" novalidate>
                @csrf
                @method('PUT')
                @include('settings.users._form')
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
                    <a href="{{ route('settings.users.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card narrow">
        <div class="card-header fw-bold"><i class="fa-solid fa-key" aria-hidden="true"></i> {{ __('users.reset_password') }}</div>
        <div class="card-body">
            <p class="form-text mt-0">{{ __('users.reset_password_hint') }}</p>
            <form method="POST" action="{{ route('settings.users.password', $user) }}" novalidate>
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="reset_password" class="form-label">{{ __('users.new_password') }}</label>
                    <input type="password" id="reset_password" name="password" required dir="ltr" autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="reset_password_confirmation" class="form-label">{{ __('users.password_confirmation') }}</label>
                    <input type="password" id="reset_password_confirmation" name="password_confirmation" required dir="ltr" autocomplete="new-password" class="form-control">
                </div>
                <button type="submit" class="btn btn-outline-primary">{{ __('users.reset_password') }}</button>
            </form>
        </div>
    </div>
@endsection
