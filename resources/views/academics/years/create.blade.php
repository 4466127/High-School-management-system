@extends('layouts.app', ['title' => __('academics.year_create')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('academics.year_create') }}</h1>
        <a href="{{ route('academics.years.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ route('academics.years.store') }}" novalidate>
        @csrf
        <div class="card form-section mb-3">
            <div class="card-header"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> {{ __('academics.year') }}</div>
            <div class="card-body">
                @include('academics.years._form')
            </div>
        </div>

        @if ($copyOptions->isNotEmpty())
            <div class="card form-section mb-3">
                <div class="card-header"><i class="fa-solid fa-copy" aria-hidden="true"></i> {{ __('academics.copy_from') }}</div>
                <div class="card-body">
                    <p class="form-text mt-0">{{ __('academics.copy_from_hint') }}</p>
                    <div class="row align-items-end">
                        <x-form.select class="col-md-5 mb-3" name="copy_from" :label="__('academics.copy_from')" :placeholder="__('academics.copy_none')"
                                       :options="$copyOptions->mapWithKeys(fn ($y) => [$y->id => \App\Support\Digits::toPersian($y->name).' ('.\App\Support\Digits::toPersian($y->sections_count).' '.__('academics.section').')'])->all()"
                                       :value="$defaultCopy" />
                        <div class="col-md-7 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="copy_assignments" value="1" id="copy_assignments" @checked(old('copy_assignments', true))>
                                <label class="form-check-label" for="copy_assignments">{{ __('academics.copy_assignments') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        <a href="{{ route('academics.years.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
    </form>
@endsection
