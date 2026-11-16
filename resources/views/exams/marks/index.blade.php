@extends('layouts.app', ['title' => __('exams.marks_title')])

@section('content')
    <h1 class="page-title">{{ __('exams.marks_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>

    @if ($noTeacherLink)
        <div class="alert alert-warning">{{ __('academics.teacher_not_linked') }}</div>
    @endif

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @elseif ($exams->isEmpty())
        <div class="card card-body text-center text-secondary py-5">
            {{ __('exams.no_exams') }}
            @can('exams.manage')
                <div class="mt-2"><a href="{{ route('exams.manage.index') }}" class="btn btn-primary btn-sm">{{ __('exams.create') }}</a></div>
            @endcan
        </div>
    @else
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label for="m-exam" class="form-label">{{ __('exams.exam') }}</label>
                    <select name="exam" id="m-exam" class="form-select">
                        @foreach ($exams as $e)
                            <option value="{{ $e->id }}" @selected($exam && $exam->id === $e->id)>{{ $e->name }} — {{ $e->status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($manage)
                    <div class="col-sm-6 col-lg-4">
                        <label for="m-section" class="form-label">{{ __('academics.section') }}</label>
                        <select name="section" id="m-section" class="form-select">
                            @foreach ($sections as $s)
                                <option value="{{ $s->id }}" @selected($section && $section->id === $s->id)>{{ $s->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-sm-3 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.view') }}</button>
                </div>
            </div>
        </form>

        @if ($exam)
            <div class="mb-2 small">
                <span class="badge {{ $exam->status->badgeClass() }}">{{ $exam->status->label() }}</span>
                {{ __('exams.max_mark') }}: <b>@fa(\App\Services\MarkService::format($exam->max_mark))</b>
                @if (! $exam->isOpen())<span class="text-secondary">· {{ $exam->isLocked() ? __('exams.blocked_locked') : __('exams.blocked_draft') }}</span>@endif
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="card card-body text-center text-secondary py-5">{{ $manage ? __('academics.no_grade_subjects') : __('exams.no_my_subjects') }}</div>
        @else
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('academics.section') }}</th>
                            <th>{{ __('academics.subject') }}</th>
                            @if ($manage)<th>{{ __('academics.teacher') }}</th>@endif
                            <th>{{ __('exams.progress') }}</th>
                            <th class="text-end"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $row)
                            @php($done = $row['students'] > 0 && $row['entered'] >= $row['students'])
                            <tr>
                                <td>{{ $row['section']->shortLabel }} <span class="small text-secondary">({{ $row['section']->shift->label() }})</span></td>
                                <td class="fw-medium">{{ $row['subject']->name }}</td>
                                @if ($manage)<td class="small">{{ $row['teacher']?->full_name ?? __('academics.no_teacher') }}</td>@endif
                                <td>
                                    <span @class(['badge', 'text-bg-success' => $done, 'text-bg-light border text-secondary' => ! $done])>
                                        @fa($row['entered']) / @fa($row['students'])
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('exams.marks.show', [$exam, $row['section'], $row['subject']]) }}" @class(['btn btn-sm', 'btn-primary' => ! $done && $exam->isOpen(), 'btn-outline-primary' => $done || ! $exam->isOpen()])>
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> {{ __('exams.open_sheet') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
