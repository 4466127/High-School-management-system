{{-- زبانه‌ی حاضری پرونده‌ی دانش‌آموز: شمار وضعیت‌ها در سال جاری و روزهای غیر «حاضر» --}}
@if (! $attendance)
    <div class="card card-body text-center text-secondary py-4">{{ __('attendance.student_no_enrollment') }}</div>
@elseif ($attendance['days'] === 0)
    <div class="card card-body text-center text-secondary py-4">{{ __('attendance.student_no_records') }}</div>
@else
    @php($percent = \App\Services\AttendanceService::percent($attendance['totals']))
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-bold mb-2">{{ __('attendance.student_summary', ['year' => \App\Support\Digits::toPersian($currentYear->name)]) }}</div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                    <span class="badge fs-6 fw-normal {{ $status->badgeClass() }}">{{ $status->label() }}: @fa($attendance['totals'][$status->value])</span>
                @endforeach
                <span class="text-secondary">· {{ __('attendance.days_recorded') }}: @fa($attendance['days'])</span>
                @if ($percent !== null)
                    <span @class(['fw-bold', 'text-danger' => $percent < 75])>· {{ __('attendance.percent') }}: @fa($percent)٪</span>
                @endif
            </div>
        </div>
    </div>

    @if ($attendance['notPresent']->isNotEmpty())
        <div class="card">
            <div class="card-header fw-bold">{{ __('attendance.student_not_present') }}</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('attendance.date') }}</th>
                        <th>{{ __('students.status') }}</th>
                        <th>{{ __('attendance.note') }}</th>
                        <th>{{ __('academics.section') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($attendance['notPresent'] as $record)
                        <tr>
                            <td>@jdate($record->session->date, 'full')</td>
                            <td><span class="badge {{ $record->status->badgeClass() }}">{{ $record->status->label() }}</span></td>
                            <td class="small">{{ $record->note }}</td>
                            <td class="small text-secondary">{{ $record->session->section->shortLabel }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
