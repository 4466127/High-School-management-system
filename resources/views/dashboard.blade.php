@extends('layouts.app', ['title' => __('menu.dashboard')])

@php
    $m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents));
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
@endphp

@section('content')
    <div class="welcome">
        <div class="welcome-date">@jdate(now(), 'full')@if ($year) · {{ __('academics.year') }} @fa($year->name)@endif</div>
        <div class="fw-bold fs-4 my-1">{{ __('app.dashboard.welcome', ['name' => $user->name]) }}</div>
        <div class="small">
            {{ __('app.dashboard.your_roles') }}:
            @forelse ($user->roles as $role)
                <span class="badge">{{ $role->label() }}</span>
            @empty
                {{ __('app.none') }}
            @endforelse
        </div>
    </div>

    @if ($license ?? null)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            <span class="flex-fill">{{ __('license.dashboard_warning', ['state' => __('license.states.'.$license)]) }}</span>
            <a href="{{ route('settings.license.index') }}" class="btn btn-sm btn-warning">{{ __('license.title') }}</a>
        </div>
    @endif

    @if ($backup)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2">
            <i class="fa-solid fa-database" aria-hidden="true"></i>
            <span class="flex-fill">{{ $backup['days'] === null ? __('backup.dashboard_none') : __('backup.dashboard_due', ['days' => $n($backup['days'])]) }}</span>
            <a href="{{ route('settings.backups.index') }}" class="btn btn-sm btn-warning">{{ __('backup.create') }}</a>
        </div>
    @endif

    {{-- اعلان‌ها (مرحله‌ی ۱۲) --}}
    @if (($announcements ?? collect())->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                <span class="fw-medium flex-grow-1">{{ __('announcements.dashboard_title') }}</span>
                <a href="{{ route('announcements.index') }}" class="small">{{ __('announcements.dashboard_all') }}</a>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @foreach ($announcements as $a)
                    <a href="{{ route('announcements.show', $a) }}" class="dash-announcement text-decoration-none text-body">
                        <div class="fw-medium">
                            @if ($a->is_pinned)<i class="fa-solid fa-thumbtack text-danger" aria-hidden="true"></i>@endif
                            {{ $a->title }}
                            <span class="badge text-bg-light border fw-normal">{{ $a->audience->label() }}</span>
                        </div>
                        <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($a->body, 140) }} · @jdate($a->published_at)</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- آمار یک‌نگاه --}}
    @if ($students || $attendance || $finance || $courses)
        <div class="row g-2 mb-3 dash-stats">
            @if ($students)
                <div class="col-6 col-lg-3">
                    <div class="card card-body py-2 h-100">
                        <div class="small text-secondary"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i> {{ $students['mine'] ? __('dashboard.my_students') : __('dashboard.students') }}</div>
                        <div class="fs-4 fw-bold">{{ $n($students['total']) }}</div>
                        <div class="small text-secondary">{{ __('dashboard.boys') }} {{ $n($students['male']) }} · {{ __('dashboard.girls') }} {{ $n($students['female']) }} · {{ __('dashboard.sections') }} {{ $n($students['sections']) }}</div>
                    </div>
                </div>
                @if ($students['staff'] !== null)
                    <div class="col-6 col-lg-3">
                        <div class="card card-body py-2 h-100">
                            <div class="small text-secondary"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> {{ __('dashboard.staff') }}</div>
                            <div class="fs-4 fw-bold">{{ $n($students['staff']) }}</div>
                        </div>
                    </div>
                @endif
            @endif
            @if ($attendance)
                <div class="col-6 col-lg-3">
                    <a href="{{ route('attendance.daily.index') }}" class="card card-body py-2 h-100 text-decoration-none">
                        <div class="small text-secondary"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> {{ __('dashboard.attendance_today') }}</div>
                        <div class="fs-4 fw-bold">{{ $attendance['percent'] !== null ? $n($attendance['percent']).'٪' : '—' }}</div>
                        <div @class(['small', 'text-danger' => $attendance['taken'] < $attendance['total'], 'text-success' => $attendance['taken'] === $attendance['total']])>
                            {{ __('dashboard.sheets_taken', ['taken' => $n($attendance['taken']), 'total' => $n($attendance['total'])]) }}
                            · {{ __('enums.attendance_status.absent') }} {{ $n($attendance['counts']['absent'] ?? 0) }}
                        </div>
                    </a>
                </div>
                @if ($attendance['high_absence'] !== null)
                    <div class="col-6 col-lg-3">
                        <a href="{{ route('attendance.absentees.index') }}" class="card card-body py-2 h-100 text-decoration-none">
                            <div class="small text-secondary"><i class="fa-solid fa-user-clock" aria-hidden="true"></i> {{ __('dashboard.high_absence') }}</div>
                            <div @class(['fs-4 fw-bold', 'text-danger' => $attendance['high_absence'] > 0])>{{ $n($attendance['high_absence']) }}</div>
                            <div class="small text-secondary">{{ __('dashboard.high_absence_hint', ['days' => $n($attendance['min_absent'])]) }}</div>
                        </a>
                    </div>
                @endif
            @endif
            @if ($finance && $finance['fees'])
                <div class="col-6 col-lg-3">
                    <div class="card card-body py-2 h-100">
                        <div class="small text-secondary"><i class="fa-solid fa-receipt" aria-hidden="true"></i> {{ __('dashboard.fees_month', ['month' => $finance['month_label']]) }}</div>
                        <div class="fs-5 fw-bold text-success">{{ $m($finance['month_received']) }}</div>
                        <div class="small text-secondary">{{ __('dashboard.today') }}: {{ $m($finance['today_received']) }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <a href="{{ route('finance.debtors.index') }}" class="card card-body py-2 h-100 text-decoration-none">
                        <div class="small text-secondary"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> {{ __('dashboard.debt') }}</div>
                        <div @class(['fs-5 fw-bold', 'text-danger' => $finance['debt'] > 0])>{{ $m($finance['debt']) }}</div>
                        <div class="small text-secondary">{{ __('dashboard.debtors', ['count' => $n($finance['debtors'])]) }}</div>
                    </a>
                </div>
            @endif
            @if ($finance && $finance['ledger'])
                <div class="col-6 col-lg-3">
                    <a href="{{ route('finance.ledger.index') }}" class="card card-body py-2 h-100 text-decoration-none">
                        <div class="small text-secondary"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> {{ __('dashboard.ledger_month', ['month' => $finance['month_label']]) }}</div>
                        <div class="small">{{ __('finance.sum_income') }}: <b class="text-success">{{ $m($finance['month_income']) }}</b></div>
                        <div class="small">{{ __('finance.sum_expense') }}: <b class="text-danger">{{ $m($finance['month_expense']) }}</b></div>
                    </a>
                </div>
            @endif
            @if ($courses)
                <div class="col-6 col-lg-3">
                    <a href="{{ route('courses.list.index') }}" class="card card-body py-2 h-100 text-decoration-none">
                        <div class="small text-secondary"><i class="fa-solid fa-people-group" aria-hidden="true"></i> {{ __('dashboard.courses_active') }}</div>
                        <div class="fs-4 fw-bold">{{ $n($courses['active']) }}</div>
                        <div class="small text-secondary">{{ __('dashboard.participants', ['count' => $n($courses['participants'])]) }}@if ($courses['planned']) · {{ __('dashboard.courses_planned', ['count' => $n($courses['planned'])]) }}@endif</div>
                    </a>
                </div>
                @if ($courses['cash_balance'] !== null)
                    <div class="col-6 col-lg-3">
                        <a href="{{ route('courses.ledger.index') }}" class="card card-body py-2 h-100 text-decoration-none">
                            <div class="small text-secondary"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> {{ __('dashboard.course_cash') }}</div>
                            <div @class(['fs-5 fw-bold', 'text-danger' => $courses['cash_balance'] < 0])>{{ $m($courses['cash_balance']) }}</div>
                        </a>
                    </div>
                @endif
            @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        {{-- حاضری امروز بخش‌ها --}}
        @if ($attendance)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center"><span class="fw-medium">{{ __('dashboard.sections_today') }}</span>
                        <a href="{{ route('attendance.daily.index') }}" class="ms-auto small">{{ __('dashboard.open') }}</a></div>
                    <ul class="list-group list-group-flush dash-list">
                        @foreach ($attendance['sections']->sortBy('taken')->take(8) as $row)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="flex-grow-1">{{ $row['section']->label }}</span>
                                @if ($row['taken'])
                                    <span class="badge text-bg-success">{{ __('attendance.taken') }}</span>
                                    @if ($row['absent'])<span class="small text-danger">{{ __('enums.attendance_status.absent') }} {{ $n($row['absent']) }}</span>@endif
                                @else
                                    <a href="{{ route('attendance.daily.show', $row['section']) }}" class="btn btn-sm btn-outline-primary">{{ __('dashboard.take_now') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($attendance['sections']->count() > 8)<div class="card-footer small text-secondary">{{ __('dashboard.more', ['count' => $n($attendance['sections']->count() - 8)]) }}</div>@endif
                </div>
            </div>
        @endif

        {{-- امتحانات --}}
        @if ($exams)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-medium">{{ __('dashboard.exams') }}</div>
                    <ul class="list-group list-group-flush dash-list">
                        @forelse ($exams['open'] as $exam)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="flex-grow-1">{{ $exam->name }}</span>
                                <span class="badge text-bg-success">{{ $exam->status->label() }}</span>
                                @can('marks.enter')<a href="{{ route('exams.marks.index', ['exam' => $exam->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('dashboard.enter_marks') }}</a>@endcan
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">{{ __('dashboard.no_open_exams') }}</li>
                        @endforelse
                        @foreach ($exams['upcoming'] as $exam)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="flex-grow-1">{{ $exam->name }}</span>
                                <span class="small text-secondary">{{ __('dashboard.starts') }} @jdate($exam->start_date)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- حاضری کورس امروز --}}
        @if ($courses && $courses['can_attendance'] && $courses['groups']->isNotEmpty())
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center"><span class="fw-medium">{{ __('dashboard.course_groups_today') }}</span>
                        <a href="{{ route('courses.attendance.index') }}" class="ms-auto small">{{ __('dashboard.open') }}</a></div>
                    <ul class="list-group list-group-flush dash-list">
                        @foreach ($courses['groups']->take(8) as $group)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="flex-grow-1">{{ $group->course->name }} — {{ $group->name }} <span class="small text-secondary">({{ $group->shift->label() }})</span></span>
                                @if (in_array($group->id, $courses['taken'], true))
                                    <span class="badge text-bg-success">{{ __('courses.taken') }}</span>
                                @else
                                    <a href="{{ route('courses.attendance.show', $group) }}" class="btn btn-sm btn-outline-primary">{{ __('dashboard.take_now') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <h2 class="section-title">{{ __('app.dashboard.quick_access') }}</h2>
    <div class="hk-tiles">
        @foreach ($tiles as $tile)
            @if ($tile['url'])
                <a href="{{ $tile['url'] }}" class="hk-tile">
                    <span class="hk-tile-icon"><i class="fa-solid {{ $tile['icon'] }}" aria-hidden="true"></i></span>
                    {{ $tile['label'] }}
                </a>
            @else
                <div class="hk-tile is-soon" aria-disabled="true">
                    <span class="hk-tile-icon"><i class="fa-solid {{ $tile['icon'] }}" aria-hidden="true"></i></span>
                    {{ $tile['label'] }}
                    <small class="text-secondary">{{ __('app.soon') }}</small>
                </div>
            @endif
        @endforeach
    </div>
@endsection
