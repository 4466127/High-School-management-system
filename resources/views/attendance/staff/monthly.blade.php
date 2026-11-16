@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('attendance.staff_monthly_title')])

@php($statuses = \App\Enums\AttendanceStatus::cases())

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('attendance.staff_monthly_title') }} — {{ $monthLabel }}</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('attendance.staff_monthly_title') }} <span class="text-secondary fs-6">— {{ $monthLabel }}</span></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('attendance.staff.monthly', ['month' => $month['key'], 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('attendance.staff.monthly.export', ['month' => $month['key']]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
                <a href="{{ route('attendance.staff.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="s-month" class="form-label">{{ __('attendance.month') }}</label>
                    <select name="month" id="s-month" class="form-select">
                        @unless (isset($monthOptions[$month['key']]))
                            <option value="{{ $month['key'] }}" selected>{{ $monthLabel }}</option>
                        @endunless
                        @foreach ($monthOptions as $key => $label)
                            <option value="{{ $key }}" @selected($key === $month['key'])>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.view') }}</button>
                </div>
            </div>
        </form>
    @endif

    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle text-center mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                <thead>
                <tr>
                    <th class="text-start">{{ __('students.full_name') }}</th>
                    <th class="text-start">{{ __('staff.staff_type') }}</th>
                    @foreach ($statuses as $status)<th>{{ $status->label() }}</th>@endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td class="text-start fw-medium">{{ $member->full_name }}</td>
                        <td class="text-start small">{{ $member->staff_type->label() }}@if ($member->position) · {{ $member->position }}@endif</td>
                        @foreach ($statuses as $status)
                            @php($n = $totals[$member->id][$status->value] ?? 0)
                            <td @class(['fw-bold text-danger' => $status === \App\Enums\AttendanceStatus::Absent && $n > 0])>@fa($n)</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 2 + count($statuses) }}" class="text-secondary py-4">{{ __('attendance.staff_empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($print)
        <div class="signatures"><div>{{ __('app.signature_principal') }}</div></div>
    @endif
@endsection
