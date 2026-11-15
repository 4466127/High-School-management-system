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
                {{-- لوگوی مکتب همراه صفحه‌ی تنظیمات مکتب اضافه می‌شود --}}
                <i class="fa-solid fa-school auth-logo" aria-hidden="true"></i>
                <h1 class="h5 fw-bold mb-0">{{ \App\Models\Setting::get('school_name') }}</h1>
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
