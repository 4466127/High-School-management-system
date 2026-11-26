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
    @include('layouts.partials.letterhead', ['withAddress' => true, 'logoSize' => 48])
    @endunless

    @yield('content')
</div>

@include('layouts.partials.alerts')
<script src="{{ asset('assets/js/print.js') }}"></script>
<script src="{{ asset('assets/js/school.js') }}"></script>
</body>
</html>
