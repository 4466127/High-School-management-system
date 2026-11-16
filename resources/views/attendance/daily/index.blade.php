@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('attendance.daily_title')])

@php
    $statuses = \App\Enums\AttendanceStatus::cases();
    $dayKey = $date->format('Y-m-d');
    $takenCount = $sessions->count();
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('attendance.daily_print_title') }} — @jdate($date, 'full')</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">
                {{ __('attendance.daily_title') }}
                <span class="text-secondary fs-6">— @jdate($date, 'full')</span>
            </h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('attendance.daily.index', ['date' => $dayKey, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('attendance.daily.export', ['date' => $dayKey]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>

        @include('attendance.partials.date-bar', ['route' => 'attendance.daily.index'])
    @endif

    @if (! $print)
        @if ($noTeacherLink)
            <div class="alert alert-warning">{{ __('academics.teacher_not_linked') }}</div>
        @endif
        @if ($date->gt($today))
            <div class="alert alert-info">{{ __('attendance.future_notice') }}</div>
        @elseif ($year && ! $inYear)
            <div class="alert alert-warning">{{ __('attendance.out_of_year_notice') }}</div>
        @endif
        @if ($isHoliday)
            <div class="alert alert-secondary"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i> {{ __('attendance.holiday_notice') }}</div>
        @endif
    @endif

    @if (! $year)
        <div class="card card-body text-center py-5">
            <div class="fs-5 fw-bold mb-1">{{ __('academics.no_year') }}</div>
            <div class="text-secondary">{{ __('academics.no_year_hint') }}</div>
        </div>
    @elseif ($sections->isEmpty())
        <div class="card card-body text-center text-secondary py-5">
            {{ $noTeacherLink || ! auth()->user()->can('attendance.edit') ? __('attendance.no_my_sections') : __('attendance.no_sections') }}
        </div>
    @else
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="fw-bold">{{ __('attendance.sections_taken', ['taken' => \App\Support\Digits::toPersian((string) $takenCount), 'total' => \App\Support\Digits::toPersian((string) $sections->count())]) }}</span>
            @foreach ($statuses as $status)
                @if ($totals[$status->value] > 0)
                    <span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}: @fa($totals[$status->value])</span>
                @endif
            @endforeach
        </div>

        @if ($print)
            <table class="table table-bordered table-sm align-middle text-center">
                <thead>
                <tr>
                    <th class="text-start">{{ __('academics.section') }}</th>
                    <th>{{ __('academics.students_label') }}</th>
                    @foreach ($statuses as $status)<th>{{ $status->label() }}</th>@endforeach
                    <th>{{ __('attendance.percent') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($sections as $section)
                    @php($c = $counts[$section->id] ?? [])
                    <tr>
                        <td class="text-start">{{ $section->label }}</td>
                        <td>@fa($section->active_enrollments_count)</td>
                        @if ($sessions->has($section->id))
                            @foreach ($statuses as $status)<td>@fa($c[$status->value] ?? 0)</td>@endforeach
                            @php($p = \App\Services\AttendanceService::percent($c))
                            <td>{{ $p !== null ? \App\Support\Digits::toPersian((string) $p).'٪' : '—' }}</td>
                        @else
                            <td colspan="{{ count($statuses) + 1 }}" class="text-secondary">{{ __('attendance.not_taken') }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="section-grid mb-4">
                @foreach ($sections as $section)
                    @php($session = $sessions->get($section->id))
                    @php($c = $counts[$section->id] ?? [])
                    <div @class(['att-card', 'is-taken' => $session])>
                        <div class="section-card-head">
                            <div>
                                <div class="fw-bold">{{ $section->gradeLevel->name }}</div>
                                <div class="small text-secondary">{{ $section->shift->label() }} · @fa($section->active_enrollments_count) {{ __('academics.students_label') }}</div>
                            </div>
                            <span class="section-badge">{{ $section->name }}</span>
                        </div>
                        @if ($session)
                            <div><span class="badge text-bg-success"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('attendance.taken') }}</span></div>
                            <div class="att-counts">
                                @foreach ($statuses as $status)
                                    @if (($c[$status->value] ?? 0) > 0)
                                        <span class="badge {{ $status->badgeClass() }}" title="{{ $status->label() }}">{{ $status->code() }} @fa($c[$status->value])</span>
                                    @endif
                                @endforeach
                            </div>
                            <div class="small text-secondary text-truncate">
                                {{ __('attendance.taken_by', ['name' => $session->takenBy?->name ?? '—', 'time' => \App\Support\Digits::toPersian($session->taken_at?->format('H:i') ?? '')]) }}
                            </div>
                        @else
                            <div><span class="badge text-bg-light border text-secondary"><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ __('attendance.not_taken') }}</span></div>
                        @endif
                        @if ($inYear && auth()->user()->can('record', [\App\Models\AttendanceSession::class, $section, $date]))
                            <a href="{{ route('attendance.daily.show', ['section' => $section, 'date' => $dayKey]) }}" @class(['btn btn-sm mt-auto', 'btn-primary' => ! $session, 'btn-outline-primary' => $session])>
                                <i class="fa-solid {{ $session ? 'fa-pen' : 'fa-clipboard-check' }}" aria-hidden="true"></i>
                                {{ $session ? __('attendance.correct') : __('attendance.take') }}
                            </a>
                        @else
                            <a href="{{ route('attendance.daily.show', ['section' => $section, 'date' => $dayKey]) }}" class="btn btn-sm btn-light mt-auto">{{ __('attendance.view_sheet') }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <h2 class="section-title">{{ __('attendance.non_present_title') }}</h2>
        @if ($nonPresent->isEmpty())
            <div @class(['text-secondary', 'card card-body' => ! $print])>{{ $takenCount ? __('attendance.non_present_empty') : __('attendance.not_taken') }}</div>
        @else
            <div @class(['card' => ! $print])>
                <div class="table-responsive">
                    <table @class(['table align-middle mb-0', 'table-bordered table-sm' => $print])>
                        <thead>
                        <tr>
                            <th>{{ __('academics.section') }}</th>
                            <th>{{ __('academics.roll_no') }}</th>
                            <th>{{ __('students.full_name') }}</th>
                            <th>{{ __('students.father_name') }}</th>
                            <th>{{ __('students.status') }}</th>
                            <th>{{ __('attendance.note') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($nonPresent as $record)
                            <tr>
                                <td>{{ $record->session->section->shortLabel }}</td>
                                <td>@fa($record->enrollment->roll_no)</td>
                                <td>{{ $record->enrollment->student->full_name }}</td>
                                <td>{{ $record->enrollment->student->father_name }}</td>
                                <td><span class="badge {{ $record->status->badgeClass() }}">{{ $record->status->label() }}</span></td>
                                <td class="small">{{ $record->note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($print)
            <div class="signatures">
                <div>{{ __('app.signature_principal') }}</div>
            </div>
        @endif
    @endif
@endsection
