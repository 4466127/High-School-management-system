<!doctype html>
<html lang="fa" dir="rtl">
<head>
    @include('layouts.partials.head')
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="card auth-card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <div class="auth-logo" aria-hidden="true">
                    @if (\App\Models\Setting::get('logo_path'))
                        <img src="{{ route('school.logo') }}" alt="">
                    @else
                        <i class="fa-solid fa-graduation-cap"></i>
                    @endif
                </div>
                <h1 class="h5 fw-bold mb-0 auth-title">{{ \App\Models\Setting::get('school_name') }}</h1>
                <div class="text-secondary small">{{ __('app.name') }}</div>
            </div>

            @include('layouts.partials.alerts')

            @yield('content')
        </div>
    </div>
</div>
@include('layouts.partials.scripts')
</body>
</html>
