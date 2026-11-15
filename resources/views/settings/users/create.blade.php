@extends('layouts.app', ['title' => __('users.create')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('users.create') }}</h1>
        <a href="{{ route('settings.users.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.users.store') }}" novalidate>
                @csrf
                @include('settings.users._form')
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
                    <a href="{{ route('settings.users.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
