@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('reports.students_title')])

@php($n = fn ($v) => \App\Support\Digits::toPersian((string) $v))

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('reports.students_title') }}</h1>
        <div class="text-center small mb-3">{{ $section?->label ?? __('reports.all_sections') }}@if ($year) · {{ __('academics.year') }} @fa($year->name)@endif</div>
    @else
        @include('reports._filters', ['title' => __('reports.students_title'), 'route' => 'reports.students.index', 'export' => 'reports.students.export', 'genderFilter' => true])
    @endif

    @if ($stats->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ $year ? __('reports.no_data') : __('academics.no_year') }}</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <div @class(['card h-100' => ! $print])>
                    <div class="card-header fw-medium">{{ __('reports.by_grade') }}</div>
                    <table class="table table-sm align-middle text-center mb-0 @if ($print) table-bordered @endif">
                        <thead><tr><th class="text-start">{{ __('academics.grade') }}</th><th>{{ __('reports.sections_count') }}</th><th>{{ __('reports.boys') }}</th><th>{{ __('reports.girls') }}</th><th>{{ __('reports.total') }}</th></tr></thead>
                        <tbody>
                        @foreach ($byGrade as $grade => $r)
                            <tr><td class="text-start">{{ $grade }}</td><td>{{ $n($r['sections']) }}</td><td>{{ $n($r['male']) }}</td><td>{{ $n($r['female']) }}</td><td class="fw-bold">{{ $n($r['total']) }}</td></tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr class="fw-bold"><td class="text-start">{{ __('reports.grand_total') }}</td><td>{{ $n($stats->count()) }}</td><td>{{ $n($stats->sum('male')) }}</td><td>{{ $n($stats->sum('female')) }}</td><td>{{ $n($stats->sum('total')) }}</td></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="col-lg-7">
                <div @class(['card h-100' => ! $print])>
                    <div class="card-header fw-medium">{{ __('reports.by_section') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle text-center mb-0 @if ($print) table-bordered @endif">
                            <thead><tr><th class="text-start">{{ __('academics.section') }}</th><th>{{ __('academics.class_teacher') }}</th><th>{{ __('reports.boys') }}</th><th>{{ __('reports.girls') }}</th><th>{{ __('reports.total') }}</th></tr></thead>
                            <tbody>
                            @foreach ($stats as $r)
                                <tr><td class="text-start">{{ $r['section']->label }}</td><td class="small">{{ $r['section']->classTeacher?->full_name ?? '—' }}</td><td>{{ $n($r['male']) }}</td><td>{{ $n($r['female']) }}</td><td class="fw-bold">{{ $n($r['total']) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($list)
            <div @class(['card' => ! $print])>
                <div class="card-header fw-medium">{{ __('reports.list_title') }} ({{ $n($list->count()) }})</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 @if ($print) table-bordered @endif">
                        <thead><tr><th>#</th><th>{{ __('academics.section') }}</th><th>{{ __('academics.roll_no') }}</th><th>{{ __('students.admission_no') }}</th><th>{{ __('students.full_name') }}</th><th>{{ __('students.father_name') }}</th><th>{{ __('reports.gender') }}</th></tr></thead>
                        <tbody>
                        @foreach ($list as $e)
                            <tr>
                                <td>{{ $n($loop->iteration) }}</td>
                                <td class="small">{{ $e->section->label }}</td>
                                <td>@fa($e->roll_no)</td>
                                <td>@fa($e->student->admission_no)</td>
                                <td class="fw-medium">{{ $e->student->full_name }}</td>
                                <td>{{ $e->student->father_name }}</td>
                                <td class="small">{{ $e->student->gender?->studentLabel() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
