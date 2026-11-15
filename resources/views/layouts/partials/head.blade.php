<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="hk-csrf" content="{{ csrf_token() }}">
<meta name="hk-base" content="{{ rtrim(url('/'), '/') }}">
<meta name="hk-today" content="{{ now(\App\Support\JalaliDate::TIMEZONE)->toDateString() }}">
<title>{{ trim(($title ?? '') !== '' ? $title.' — ' : '') }}{{ \App\Models\Setting::get('school_name') }}</title>
<link rel="icon" href="{{ asset('assets/icons/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.rtl.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/school.css') }}">
