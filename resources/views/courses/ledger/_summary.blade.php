{{-- جمع‌های دفتر مالی یک کورس ($summary از CourseLedgerService::summary؛ $teacherNames اختیاری) --}}
@php($m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents)))
<div class="row g-2 mb-3">
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-secondary">{{ __('courses.sum_donations') }}</div><div class="fw-bold text-success">{{ $m($summary['donations_total']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-secondary">{{ __('courses.sum_fees') }}</div><div class="fw-bold text-success">{{ $m($summary['fees']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-secondary">{{ __('courses.sum_donations_direct') }}</div><div class="fw-bold">{{ $m($summary['donations_direct']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-secondary">{{ __('courses.sum_salaries') }}</div><div class="fw-bold text-primary">{{ $m($summary['salaries']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-secondary">{{ __('courses.sum_expenses') }}</div><div class="fw-bold text-danger">{{ $m($summary['expenses']) }}</div></div></div>
    <div class="col-12 col-lg"><div class="card card-body py-2 border-success"><div class="small text-secondary">{{ __('courses.sum_balance') }}</div><div @class(['fw-bold fs-5', 'text-danger' => $summary['balance'] < 0])>{{ $m($summary['balance']) }}</div></div></div>
</div>

@if (! empty($teacherNames) && $summary['teachers'] !== [])
    <div class="card mb-3">
        <div class="card-header fw-medium">{{ __('courses.teachers_received') }}</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('courses.teacher') }}</th><th class="text-end">{{ __('courses.received_salary') }}</th><th class="text-end">{{ __('courses.received_direct') }}</th><th class="text-end">{{ __('courses.received_total') }}</th></tr></thead>
                <tbody>
                @foreach ($summary['teachers'] as $staffId => $t)
                    <tr>
                        <td>{{ $teacherNames[$staffId] ?? '—' }}</td>
                        <td class="text-end">{{ $m($t['salary']) }}</td>
                        <td class="text-end">{{ $m($t['direct']) }}</td>
                        <td class="text-end fw-bold">{{ $m($t['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
