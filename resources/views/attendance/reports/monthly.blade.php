@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('attendance.monthly_title')])

@php
    $statuses = \App\Enums\AttendanceStatus::cases();
    $query = array_filter(['section' => $section?->id, 'month' => $month['key']]);
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('attendance.monthly_title') }} — {{ $monthLabel }}</h1>
        <div class="text-center mb-3">
            {{ $section ? $section->label : __('attendance.summary_title') }}
            @if ($section?->classTeacher) · {{ __('academics.class_teacher') }}: {{ $section->classTeacher->full_name }}@endif
        </div>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('attendance.monthly_title') }} <span class="text-secondary fs-6">— {{ $monthLabel }}</span></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('attendance.monthly.index', $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('attendance.monthly.export', $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label for="m-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="m-section" class="form-select">
                        <option value="">{{ __('attendance.section_all') }}</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <label for="m-month" class="form-label">{{ __('attendance.month') }}</label>
                    <select name="month" id="m-month" class="form-select">
                        @unless (isset($monthOptions[$month['key']]))
                            <option value="{{ $month['key'] }}" selected>{{ $monthLabel }}</option>
                        @endunless
                        @foreach ($monthOptions as $key => $label)
                            <option value="{{ $key }}" @selected($key === $month['key'])>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-2 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.view') }}</button>
                </div>
            </div>
        </form>
    @endif

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @elseif ($section)
        {{-- جدول ماهوار یک بخش --}}
        @if ($sessions->isEmpty())
            <div class="alert alert-light border">{{ __('attendance.no_data_month') }}</div>
        @endif
        <div @class(['card' => ! $print, 'print-landscape' => $print])>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center mb-0 att-grid">
                    <thead>
                    <tr>
                        <th>{{ __('academics.roll_no') }}</th>
                        <th class="text-start att-grid-name">{{ __('students.full_name') }}</th>
                        @foreach ($days as $day)
                            @php($key = $day->format('Y-m-d'))
                            <th @class(['att-holiday' => in_array($key, $holidays, true), 'att-taken' => $sessions->has($key)])>@fa(\App\Support\JalaliDate::fromGregorian($day)[2])</th>
                        @endforeach
                        @foreach ($statuses as $status)
                            <th class="att-total" title="{{ $status->label() }}">{{ $status->code() }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>@fa($row['enrollment']->roll_no)</td>
                            <td class="text-start att-grid-name">{{ $row['enrollment']->student->full_name }}</td>
                            @foreach ($days as $day)
                                @php($key = $day->format('Y-m-d'))
                                @php($mark = $row['marks'][$key] ?? null)
                                <td @class(['att-holiday' => in_array($key, $holidays, true), 'att-mark-'.$mark?->value => $mark])>{{ $mark?->code() }}</td>
                            @endforeach
                            @foreach ($statuses as $status)
                                <td class="att-total fw-medium">@fa($row['totals'][$status->value])</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ 2 + count($days) + count($statuses) }}" class="text-secondary py-3">{{ __('academics.no_students_in_section') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="small text-secondary mt-2">
            {{ __('attendance.legend') }}:
            @foreach ($statuses as $status) <span class="me-2"><b>{{ $status->code() }}</b> = {{ $status->label() }}</span>@endforeach
            <span class="att-holiday-swatch"></span> {{ __('attendance.holiday') }}
            · {{ __('attendance.days_taken') }}: @fa($sessions->count())
        </div>
        @if ($print)
            <div class="signatures">
                <div>{{ __('app.signature_teacher') }}</div>
                <div>{{ __('app.signature_principal') }}</div>
            </div>
        @endif
    @else
        {{-- خلاصه‌ی صنف‌ها --}}
        <h2 class="section-title">{{ __('attendance.summary_title') }}</h2>
        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table @class(['table align-middle text-center mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                    <thead>
                    <tr>
                        <th class="text-start">{{ __('academics.section') }}</th>
                        <th>{{ __('academics.students_label') }}</th>
                        <th>{{ __('attendance.days_taken') }}</th>
                        @foreach ($statuses as $status)<th>{{ $status->label() }}</th>@endforeach
                        <th>{{ __('attendance.percent') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($sections as $s)
                        @php($sum = $summary[$s->id])
                        @php($p = \App\Services\AttendanceService::percent($sum['counts']))
                        <tr>
                            <td class="text-start">
                                @if ($print)
                                    {{ $s->label }}
                                @else
                                    <a href="{{ route('attendance.monthly.index', ['section' => $s->id, 'month' => $month['key']]) }}" class="fw-medium text-decoration-none">{{ $s->label }}</a>
                                @endif
                            </td>
                            <td>@fa($s->active_enrollments_count)</td>
                            <td>@fa($sum['days'])</td>
                            @foreach ($statuses as $status)<td>@fa($sum['counts'][$status->value])</td>@endforeach
                            <td @class(['fw-bold', 'text-danger' => $p !== null && $p < 75])>{{ $p !== null ? \App\Support\Digits::toPersian((string) $p).'٪' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($statuses) }}" class="text-secondary py-4">{{ __('attendance.no_my_sections') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
