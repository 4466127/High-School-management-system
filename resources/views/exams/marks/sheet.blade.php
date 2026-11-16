@extends('layouts.app', ['title' => __('exams.sheet_title').' — '.$subject->name])

@php
    $max = \App\Services\MarkService::format($exam->max_mark);
@endphp

@section('content')
    <div class="card profile-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="section-badge section-badge-lg">{{ $section->name }}</span>
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ $subject->name }} — {{ $section->gradeLevel->name }} {{ $section->name }}</h1>
                <div class="text-secondary small">
                    {{ $exam->name }} · {{ __('exams.max_mark') }}: <b>@fa($max)</b>
                    · <span class="badge {{ $exam->status->badgeClass() }}">{{ $exam->status->label() }}</span>
                </div>
            </div>
            <a href="{{ route('exams.marks.index', ['exam' => $exam->id, 'section' => $section->id]) }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>

    @if (! $canEdit)
        <div class="alert alert-info"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ __('exams.read_only') }} — {{ $blocked }}</div>
    @else
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('exams.sheet_hint', ['max' => \App\Support\Digits::toPersian($max)]) }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ __('exams.sheet_errors') }}</div>
    @endif

    @if ($enrollments->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_students_in_section') }}</div>
    @else
        <form method="POST" action="{{ route('exams.marks.store', [$exam, $section, $subject]) }}" novalidate>
            @csrf
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 marks-table">
                        <thead>
                        <tr>
                            <th style="width: 60px">{{ __('academics.roll_no') }}</th>
                            <th>{{ __('students.full_name') }}</th>
                            <th style="width: 150px">{{ __('exams.mark') }} (@fa($max))</th>
                            <th style="width: 90px" class="text-center">{{ __('exams.absent') }}</th>
                            <th>{{ __('attendance.note') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($enrollments as $id => $enrollment)
                            @php
                                $mark = $marks->get($id);
                                $value = old('marks.'.$id, $mark && $mark->mark !== null ? \App\Services\MarkService::format($mark->mark) : '');
                                $isAbsent = (bool) old('absent.'.$id, $mark?->is_absent);
                            @endphp
                            <tr>
                                <td class="fw-bold">@fa($enrollment->roll_no)</td>
                                <td>
                                    <div class="fw-medium">{{ $enrollment->student->full_name }}</div>
                                    <div class="small text-secondary">{{ __('students.father_name') }}: {{ $enrollment->student->father_name }}</div>
                                </td>
                                @if ($canEdit)
                                    <td>
                                        <input type="text" name="marks[{{ $id }}]" value="{{ $value }}" inputmode="decimal" dir="ltr" autocomplete="off"
                                               @class(['form-control mark-input', 'is-invalid' => $errors->has('marks.'.$id)])
                                               aria-label="{{ __('exams.mark') }} — {{ $enrollment->student->full_name }}" placeholder="—">
                                        @error('marks.'.$id)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="absent[{{ $id }}]" value="0">
                                        <input class="form-check-input mark-absent" type="checkbox" name="absent[{{ $id }}]" value="1" @checked($isAbsent) aria-label="{{ __('exams.absent') }} — {{ $enrollment->student->full_name }}">
                                    </td>
                                    <td>
                                        <input type="text" name="note[{{ $id }}]" value="{{ old('note.'.$id, $mark?->note) }}" maxlength="255" class="form-control form-control-sm" placeholder="{{ __('exams.note_ph') }}" aria-label="{{ __('attendance.note') }}">
                                    </td>
                                @else
                                    <td class="fw-bold">{{ $mark ? ($mark->is_absent ? __('exams.absent') : (\App\Support\Digits::toPersian(\App\Services\MarkService::format($mark->mark)) ?: '—')) : '—' }}</td>
                                    <td></td>
                                    <td class="small text-secondary">{{ $mark?->note }}</td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($canEdit)
                <div class="att-savebar">
                    <span class="small text-secondary">{{ __('exams.save_hint') }}</span>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('exams.save_marks') }}</button>
                </div>
            @endif
        </form>
    @endif
@endsection
