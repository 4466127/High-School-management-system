@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('attendance.absentees_title')])

@php($query = array_filter(['section' => $section?->id, 'min' => $min !== $defaultMin ? $min : null]))

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('attendance.absentees_title') }}@if ($year) — {{ __('academics.year') }} @fa($year->name)@endif</h1>
        <div class="text-center small mb-3">{{ $section ? $section->label.' · ' : '' }}{{ __('attendance.absentees_hint', ['min' => \App\Support\Digits::toPersian((string) $min)]) }}</div>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('attendance.absentees_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('attendance.absentees.index', $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('attendance.absentees.export', $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label for="a-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="a-section" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <label for="a-min" class="form-label">{{ __('attendance.min_absent') }}</label>
                    <input type="number" name="min" id="a-min" value="{{ $min }}" min="1" max="365" class="form-control">
                </div>
                <div class="col-sm-3 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.view') }}</button>
                </div>
            </div>
            <div class="form-text mt-2">
                {{ __('attendance.absentees_hint', ['min' => \App\Support\Digits::toPersian((string) $min)]) }}
                @if ($deprivation > 0) {{ __('attendance.deprivation_hint', ['days' => \App\Support\Digits::toPersian((string) $deprivation)]) }}@endif
            </div>
        </form>
    @endif

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @elseif ($rows->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('attendance.absentees_empty') }}</div>
    @else
        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table @class(['table align-middle text-center mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th class="text-start">{{ __('students.full_name') }}</th>
                        <th class="text-start">{{ __('students.father_name') }}</th>
                        <th class="text-start">{{ __('academics.section') }}</th>
                        <th>{{ \App\Enums\AttendanceStatus::Absent->label() }}</th>
                        <th>{{ \App\Enums\AttendanceStatus::Excused->label() }}</th>
                        <th>{{ \App\Enums\AttendanceStatus::Sick->label() }}</th>
                        <th>{{ \App\Enums\AttendanceStatus::Late->label() }}</th>
                        <th>{{ __('attendance.days_recorded') }}</th>
                        @if ($deprivation > 0)<th></th>@endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $i => $row)
                        @php($student = $row['enrollment']->student)
                        <tr>
                            <td>@fa($i + 1)</td>
                            <td class="text-start">
                                @if (! $print && auth()->user()->can('view', $student))
                                    <a href="{{ route('students.show', $student) }}#attendance" class="fw-medium text-decoration-none">{{ $student->full_name }}</a>
                                @else
                                    {{ $student->full_name }}
                                @endif
                            </td>
                            <td class="text-start">{{ $student->father_name }}</td>
                            <td class="text-start">{{ $row['enrollment']->section->shortLabel }}</td>
                            <td class="fw-bold text-danger">@fa($row['totals']->absent)</td>
                            <td>@fa($row['totals']->excused)</td>
                            <td>@fa($row['totals']->sick)</td>
                            <td>@fa($row['totals']->late)</td>
                            <td>@fa($row['totals']->total)</td>
                            @if ($deprivation > 0)
                                <td>@if ($row['deprived'])<span class="badge text-bg-danger">{{ __('attendance.deprived') }}</span>@endif</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if ($print)
            <div class="signatures"><div>{{ __('app.signature_principal') }}</div></div>
        @endif
    @endif
@endsection
