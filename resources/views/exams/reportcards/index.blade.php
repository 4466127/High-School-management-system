@extends('layouts.app', ['title' => __('exams.reportcards_title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('exams.reportcards_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
        @if ($section && $enrollments->isNotEmpty())
            <a href="{{ route('exams.reportcards.section', $section) }}" class="btn btn-primary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('exams.print_all') }}</a>
        @endif
    </div>

    @if (! $year || $sections->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ $year ? __('attendance.no_my_sections') : __('academics.no_year') }}</div>
    @else
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-8 col-lg-5">
                    <label for="rc-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="rc-section" class="form-select">
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" @selected($section && $section->id === $s->id)>{{ $s->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <button type="submit" class="btn btn-outline-primary w-100">{{ __('app.view') }}</button>
                </div>
            </div>
        </form>

        <div @class(['alert small', 'alert-success' => $finalized, 'alert-warning' => ! $finalized])>
            {{ $finalized ? __('exams.finalized_notice') : __('exams.provisional_card_notice') }}
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width: 70px">{{ __('academics.roll_no') }}</th>
                        <th>{{ __('students.full_name') }}</th>
                        <th>{{ __('students.father_name') }}</th>
                        <th>{{ __('exams.result') }}</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($enrollments as $enrollment)
                        <tr>
                            <td class="fw-bold">@fa($enrollment->roll_no)</td>
                            <td class="fw-medium">{{ $enrollment->student->full_name }}</td>
                            <td>{{ $enrollment->student->father_name }}</td>
                            <td>
                                @if ($enrollment->final_result)
                                    <span class="badge {{ $enrollment->final_result->badgeClass() }}">{{ $enrollment->final_result->label() }}</span>
                                    @if ($enrollment->class_rank)<span class="small text-secondary">· {{ __('exams.rank') }} @fa($enrollment->class_rank)</span>@endif
                                @else
                                    <span class="small text-secondary">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('exams.reportcards.show', $enrollment) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('exams.print_card') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('academics.no_students_in_section') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
