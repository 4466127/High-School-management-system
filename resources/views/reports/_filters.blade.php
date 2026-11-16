{{-- فیلتر گزارش‌ها و دکمه‌های چاپ/CSV. $title، $route = مسیر صفحه، $export = مسیر CSV؛ اختیاری: $sections، $dates (bool)، $genderFilter (bool) --}}
@php
    $query = array_filter(request()->only(['section', 'from', 'to', 'gender', 'list']));
@endphp
<div class="page-head">
    <h1 class="page-title mb-0">{{ $title }}</h1>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route($route, $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
        <a href="{{ route($export, $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
    </div>
</div>
<form method="GET" action="{{ route($route) }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        @isset($sections)
            <div class="col-sm-6 col-lg-3">
                <label for="r-section" class="form-label">{{ __('academics.section') }}</label>
                <select name="section" id="r-section" class="form-select">
                    <option value="">{{ __('reports.all_sections') }}</option>
                    @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($section && $section->id === $s->id)>{{ $s->label }}</option>@endforeach
                </select>
            </div>
        @endisset
        @if ($dates ?? false)
            <div class="col-sm-3 col-lg-2"><label class="form-label" for="r-from">{{ __('reports.from') }}</label><input type="text" id="r-from" name="from" value="{{ \App\Support\JalaliDate::format($from) }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            <div class="col-sm-3 col-lg-2"><label class="form-label" for="r-to">{{ __('reports.to') }}</label><input type="text" id="r-to" name="to" value="{{ \App\Support\JalaliDate::format($to) }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
        @endif
        @if ($genderFilter ?? false)
            <div class="col-sm-3 col-lg-2">
                <label for="r-gender" class="form-label">{{ __('reports.gender') }}</label>
                <select name="gender" id="r-gender" class="form-select">
                    <option value="">{{ __('reports.all_genders') }}</option>
                    @foreach (\App\Enums\Gender::studentOptions() as $v => $l)<option value="{{ $v }}" @selected($gender === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-3 col-lg-2">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="list" value="1" id="r-list" @checked(request()->boolean('list'))><label class="form-check-label small" for="r-list">{{ __('reports.show_list') }}</label></div>
            </div>
        @endif
        <div class="col-sm-3 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.view') }}</button></div>
    </div>
</form>
