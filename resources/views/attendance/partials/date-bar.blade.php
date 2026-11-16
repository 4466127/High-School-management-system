{{-- انتخاب روز: روز قبل / خانه‌ی تاریخ شمسی / روز بعد / امروز. $route = نام مسیر صفحه؛ $params = پارامترهای دیگر آدرس --}}
@php($params = $params ?? [])
<form method="GET" action="{{ route($route, $params) }}" class="card card-body date-bar mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route($route, $params + ['date' => $date->subDay()->format('Y-m-d')]) }}" class="btn btn-light" title="{{ __('attendance.prev_day') }}">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="d-none d-sm-inline"> {{ __('attendance.prev_day') }}</span>
        </a>
        <label for="att-date" class="visually-hidden">{{ __('attendance.date') }}</label>
        <input type="text" id="att-date" name="date" value="{{ \App\Support\JalaliDate::format($date) }}" data-jdate autocomplete="off" dir="ltr"
               class="form-control date-bar-input" placeholder="{{ __('app.date_placeholder') }}">
        <button type="submit" class="btn btn-outline-primary">{{ __('attendance.show_date') }}</button>
        @if ($date->lt($today))
            <a href="{{ route($route, $params + ['date' => $date->addDay()->format('Y-m-d')]) }}" class="btn btn-light" title="{{ __('attendance.next_day') }}">
                <span class="d-none d-sm-inline">{{ __('attendance.next_day') }} </span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </a>
        @endif
        @unless ($date->isSameDay($today))
            <a href="{{ route($route, $params) }}" class="btn btn-primary">{{ __('attendance.go_today') }}</a>
        @endunless
    </div>
</form>
