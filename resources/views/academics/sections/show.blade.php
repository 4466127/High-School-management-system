@extends('layouts.app', ['title' => $section->label])

@section('content')
    <div class="card profile-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="section-badge section-badge-lg">{{ $section->name }}</span>
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ $section->gradeLevel->name }} — {{ $section->name }}</h1>
                <div class="text-secondary small">
                    {{ __('academics.year') }} @fa($section->academicYear->name) · {{ $section->shift->label() }}
                    @if ($section->room) · {{ $section->room }}@endif
                    · {{ __('academics.class_teacher') }}: {{ $section->classTeacher?->full_name ?? __('academics.no_class_teacher') }}
                </div>
                <div class="mt-1"><span class="badge text-bg-primary">{{ __('academics.students_count', ['count' => \App\Support\Digits::toPersian($enrollments->count())]) }}</span></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($canManage)
                    <a href="{{ route('academics.sections.enroll', $section) }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('academics.enroll_students') }}</a>
                    <a href="{{ route('academics.sections.edit', $section) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                @endif
                <a href="{{ route('academics.sections.index', ['year' => $section->academic_year_id]) }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#students-pane" type="button" role="tab">{{ __('academics.tab_students') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teachers-pane" type="button" role="tab">{{ __('academics.tab_teachers') }}</button>
        </li>
        @if ($leftEnrollments->isNotEmpty())
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#left-pane" type="button" role="tab">{{ __('academics.tab_left') }} <span class="badge text-bg-light border">@fa($leftEnrollments->count())</span></button>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="students-pane" role="tabpanel">
            @if ($canManage && $enrollments->isNotEmpty())
                <form method="POST" action="{{ route('academics.sections.renumber', $section) }}" class="mb-2 text-start" data-confirm="{{ __('academics.confirm_renumber') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i> {{ __('academics.renumber') }}</button>
                </form>
            @endif
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th style="width: 70px">{{ __('academics.roll_no') }}</th>
                            <th style="width: 56px"></th>
                            <th>{{ __('students.full_name') }}</th>
                            <th>{{ __('students.father_name') }}</th>
                            <th>{{ __('students.admission_no') }}</th>
                            <th>{{ __('students.gender') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($enrollments as $enrollment)
                            @php($student = $enrollment->student)
                            <tr>
                                <td class="fw-bold">@fa($enrollment->roll_no)</td>
                                <td><x-avatar :src="$student->photo_path && $canSeeStudents ? route('students.photo', $student) : null" size="36" /></td>
                                <td>
                                    @if ($canSeeStudents)
                                        <a href="{{ route('students.show', $student) }}" class="text-decoration-none fw-medium">{{ $student->full_name }}</a>
                                    @else
                                        {{ $student->full_name }}
                                    @endif
                                </td>
                                <td>{{ $student->father_name }}</td>
                                <td>@fa($student->admission_no)</td>
                                <td>{{ $student->gender->studentLabel() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('academics.no_students_in_section') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="teachers-pane" role="tabpanel">
            @if ($section->gradeLevel->subjects->isEmpty())
                <div class="card card-body text-center text-secondary py-4">
                    {{ __('academics.no_grade_subjects') }}
                    @can('academics.manage')
                        <div class="mt-2"><a href="{{ route('academics.grades.subjects', $section->gradeLevel) }}" class="btn btn-sm btn-primary">{{ __('academics.set_grade_subjects') }}</a></div>
                    @endcan
                </div>
            @else
                <form method="POST" action="{{ route('academics.sections.assignments', $section) }}" class="card">
                    @csrf
                    @method('PUT')
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('academics.subject') }}</th>
                                <th>{{ __('academics.teacher') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($section->gradeLevel->subjects as $subject)
                                @php($assigned = $assignments[$subject->id] ?? null)
                                <tr>
                                    <td class="fw-medium">{{ $subject->name }}</td>
                                    <td>
                                        @if ($canManage)
                                            <select name="teachers[{{ $subject->id }}]" class="form-select" aria-label="{{ __('academics.teacher') }} {{ $subject->name }}">
                                                <option value="">{{ __('academics.no_teacher') }}</option>
                                                @foreach ($teachers as $teacher)
                                                    <option value="{{ $teacher->id }}" @selected($assigned?->staff_id === $teacher->id)>
                                                        {{ $teacher->full_name }}@if ($teacher->specialization) — {{ $teacher->specialization }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $assigned?->teacher?->full_name ?? __('academics.no_teacher') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($canManage)
                        <div class="card-footer bg-transparent">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>

        @if ($leftEnrollments->isNotEmpty())
            <div class="tab-pane fade" id="left-pane" role="tabpanel">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('students.full_name') }}</th>
                                <th>{{ __('students.status') }}</th>
                                <th>{{ __('academics.left_on') }}</th>
                                <th>{{ __('academics.leave_reason') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($leftEnrollments as $enrollment)
                                <tr>
                                    <td>{{ $enrollment->student->full_name }}</td>
                                    <td><span class="badge {{ $enrollment->status->badgeClass() }}">{{ $enrollment->status->label() }}</span></td>
                                    <td class="small">@jdate($enrollment->left_on)</td>
                                    <td class="small">{{ $enrollment->leave_reason }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
