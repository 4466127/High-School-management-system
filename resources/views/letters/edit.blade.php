@extends('layouts.app', ['title' => __('letters.edit_outgoing')])

@section('content')
    <h1 class="page-title">{{ __('letters.edit_outgoing') }}</h1>

    <form method="POST" action="{{ route('letters.outgoing.update', $letter) }}" class="card form-section" novalidate>
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('letters._form')
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('letters.save_draft') }}</button>
            <a href="{{ route('letters.outgoing.show', $letter) }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
