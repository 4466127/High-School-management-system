@extends('layouts.app', ['title' => __('import.title')])

@section('content')
    <h1 class="page-title">{{ __('import.title') }}</h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('import.intro') }}</div>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" class="card form-section" novalidate>
                @csrf
                <div class="card-header"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> {{ __('import.check') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-label">{{ __('import.type') }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($types as $t)
                                <input type="radio" class="btn-check" name="type" id="type-{{ $t }}" value="{{ $t }}" @checked(old('type', $type) === $t)>
                                <label class="btn btn-outline-primary" for="type-{{ $t }}">
                                    <i class="fa-solid {{ $t === 'students' ? 'fa-user-graduate' : 'fa-chalkboard-user' }}" aria-hidden="true"></i> {{ __('import.type_'.$t) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <x-form.input name="file" type="file" :label="__('import.file')" accept=".xlsx,.csv" required :help="__('import.file_hint')" />
                </div>
                <div class="card-footer bg-transparent">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('import.check') }}</button>
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <ol class="small ps-3 mb-3">
                        @foreach (__('import.steps') as $step)<li class="mb-1">{{ $step }}</li>@endforeach
                    </ol>
                    <div class="d-grid gap-2">
                        @foreach ($types as $t)
                            <a href="{{ route('import.template', $t) }}" class="btn btn-outline-success">
                                <i class="fa-solid fa-download" aria-hidden="true"></i> {{ __('import.download_template') }} — {{ __('import.type_'.$t) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
