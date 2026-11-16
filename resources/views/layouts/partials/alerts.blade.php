{{--
    اعلان‌ها (toast) گوشه‌ی بالای صفحه: سبز = موفق (۱۰ ثانیه)، زرد = هشدار، سرخ = ناموفق با دلیل (۱۵ ثانیه).
    school.js آن‌ها را بعد از زمان معین می‌بندد؛ با دکمه‌ی × هم بسته می‌شوند. بدون JavaScript همان‌جا می‌مانند.
--}}
@php
    $toasts = [];
    foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $type) {
        if (session($key)) {
            $toasts[] = ['type' => $type, 'text' => session($key), 'list' => []];
        }
    }
    if (isset($errors) && $errors->any() && ! session('error')) {
        $toasts[] = ['type' => 'danger', 'text' => __('app.form_has_errors'), 'list' => array_slice(array_unique($errors->all()), 0, 4)];
    }
    $icons = ['success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'danger' => 'fa-circle-xmark'];
@endphp

@if ($toasts)
    <div class="toast-stack" aria-live="polite" aria-atomic="false">
        @foreach ($toasts as $toast)
            <div class="app-toast app-toast-{{ $toast['type'] }}" role="{{ $toast['type'] === 'danger' ? 'alert' : 'status' }}"
                 data-toast-ms="{{ $toast['type'] === 'success' ? 10000 : 15000 }}">
                <i class="fa-solid {{ $icons[$toast['type']] }} app-toast-icon" aria-hidden="true"></i>
                <div class="app-toast-body">
                    <div>{{ $toast['text'] }}</div>
                    @if ($toast['list'])
                        <ul class="app-toast-list">
                            @foreach ($toast['list'] as $line)<li>{{ $line }}</li>@endforeach
                        </ul>
                    @endif
                </div>
                <button type="button" class="app-toast-close" data-toast-close aria-label="{{ __('app.close') }}">&times;</button>
                <span class="app-toast-bar" aria-hidden="true"></span>
            </div>
        @endforeach
    </div>
@endif
