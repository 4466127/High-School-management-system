{{-- صفحه‌ی خطا بدون وابستگی به پایگاه داده (تا اگر پایگاه داده قطع بود هم نمایش داده شود) --}}
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.errors.'.$code.'_title') }} — {{ __('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/school.css') }}">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="card auth-card shadow-sm">
        <div class="card-body p-4 text-center">
            <div class="error-code mb-2">@fa($code)</div>
            <h1 class="h5 fw-bold">{{ __('app.errors.'.$code.'_title') }}</h1>
            <p class="text-secondary">{{ __('app.errors.'.$code.'_text') }}</p>
            <a href="{{ url('/') }}" class="btn btn-primary">{{ __('app.errors.go_home') }}</a>
        </div>
    </div>
</div>
</body>
</html>
