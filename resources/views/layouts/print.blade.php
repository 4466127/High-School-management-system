<!doctype html>
<html lang="fa" dir="rtl">
<head>
    @include('layouts.partials.head')
</head>
<body class="print-body">
<div class="print-tools noprint">
    <button type="button" class="btn btn-primary" data-print><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('app.print') }}</button>
    <a href="{{ url()->previous() }}" class="btn btn-light">{{ __('app.back') }}</a>
</div>

<div class="print-page">
    @unless ($noHeader ?? false)
    <header class="print-head">
        <div>
            <div class="print-school">{{ \App\Models\Setting::get('school_name') }}</div>
            <div class="small">{{ collect([\App\Models\Setting::get('province'), \App\Models\Setting::get('district'), \App\Models\Setting::get('address')])->filter()->join('، ') }}</div>
        </div>
        <div class="text-start small">
            {{ __('app.printed_on') }}: @jdate(now())
        </div>
    </header>
    @endunless

    @yield('content')
</div>

@include('layouts.partials.alerts')
<script src="{{ asset('assets/js/print.js') }}"></script>
<script src="{{ asset('assets/js/school.js') }}"></script>
</body>
</html>
