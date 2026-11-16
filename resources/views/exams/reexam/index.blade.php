@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('exams.reexam_title')])

@php
    $fmt = fn ($n) => $n === null ? '—' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n));
    $open = $rows->reject(fn ($row) => $row['finalized']);
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('exams.reexam_title') }}</h1>
        <div class="text-center mb-3">{{ $section?->label }} · {{ __('academics.year') }} @fa($year?->name)</div>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('exams.reexam_title') }}</h1>
            @if ($section && $rows->isNotEmpty())
                <a href="{{ route('exams.reexam.index', ['section' => $section->id, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
            @endif
        </div>
        <p class="text-secondary small">{{ __('exams.reexam_intro') }}</p>

        @if ($sections->isNotEmpty())
            <form method="GET" class="card card-body mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-8 col-lg-5">
                        <label for="rx-section" class="form-label">{{ __('academics.section') }}</label>
                        <select name="section" id="rx-section" class="form-select">
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
        @endif
    @endif

    @if (! $year || ! $section)
        <div class="card card-body text-center text-secondary py-5">{{ $year ? __('attendance.no_my_sections') : __('academics.no_year') }}</div>
    @elseif (! $resultsFinalized)
        <div class="card card-body text-center text-secondary py-5">{{ __('exams.reexam_needs_final') }}</div>
    @elseif ($rows->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('exams.reexam_none') }}</div>
    @else
        @if ($reexamFinalized && ! $print)
            <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
                <i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('exams.reexam_finalized_notice') }}
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-bs-toggle="modal" data-bs-target="#reexamUnfinalizeModal">{{ __('exams.reexam_unfinalize') }}</button>
            </div>
        @endif

        <form method="POST" action="{{ route('exams.reexam.store', $section) }}" id="reexam-form">
            @csrf
            <div @class(['card' => ! $print])>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                        <tr>
                            <th style="width: 60px">{{ __('academics.roll_no') }}</th>
                            <th>{{ __('students.full_name') }}</th>
                            <th>{{ __('exams.reexam_subjects') }}</th>
                            <th style="width: 170px">{{ __('exams.expected_result') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $row)
                            @php
                                $enrollment = $row['enrollment'];
                                $outcome = $row['outcome'];
                                $editable = ! $row['finalized'] && ! $print;
                            @endphp
                            <tr>
                                <td class="fw-bold">@fa($enrollment->roll_no)</td>
                                <td>
                                    <div class="fw-medium">{{ $enrollment->student->full_name }}</div>
                                    <div class="small text-secondary">{{ __('students.father_name') }}: {{ $enrollment->student->father_name }}</div>
                                </td>
                                <td>
                                    @foreach ($row['subjects'] as $subject)
                                        @php
                                            $key = $enrollment->id.'.'.$subject['id'];
                                            $mark = $subject['mark'];
                                            $value = old('marks.'.$key, $mark && $mark->mark !== null ? \App\Services\MarkService::format($mark->mark) : '');
                                            $isAbsent = (bool) old('absent.'.$key, $mark?->is_absent);
                                        @endphp
                                        <div class="d-flex flex-wrap align-items-center gap-2 py-1">
                                            <span class="fw-medium" style="min-width: 110px">{{ $subject['name'] }}</span>
                                            <span class="small text-secondary">{{ __('exams.original_final') }}: <span class="text-danger">{{ $fmt($subject['final']) }}</span></span>
                                            @if ($editable)
                                                <input type="text" name="marks[{{ $enrollment->id }}][{{ $subject['id'] }}]" value="{{ $value }}" inputmode="decimal" dir="ltr" autocomplete="off"
                                                       @class(['form-control form-control-sm mark-input', 'is-invalid' => $errors->has('marks.'.$key)]) style="width: 90px"
                                                       aria-label="{{ __('exams.reexam_mark') }} — {{ $subject['name'] }} — {{ $enrollment->student->full_name }}" placeholder="—">
                                                <label class="form-check-label small">
                                                    <input type="hidden" name="absent[{{ $enrollment->id }}][{{ $subject['id'] }}]" value="0">
                                                    <input class="form-check-input mark-absent" type="checkbox" name="absent[{{ $enrollment->id }}][{{ $subject['id'] }}]" value="1" @checked($isAbsent)>
                                                    {{ __('exams.absent') }}
                                                </label>
                                                @error('marks.'.$key)<div class="text-danger small w-100">{{ $message }}</div>@enderror
                                            @else
                                                <span>{{ __('exams.reexam_mark') }}: <b>{{ $mark ? ($mark->is_absent ? __('exams.absent') : $fmt($mark->mark === null ? null : (float) $mark->mark)) : '—' }}</b></span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td>
                                    @if ($outcome['result'])
                                        <span class="badge {{ $outcome['result']->badgeClass() }}">{{ $outcome['result']->label() }}</span>
                                        @if ($outcome['grade'])<span class="small">· {{ __('exams.grade') }} {{ __('exams.grades.'.$outcome['grade']) }}</span>@endif
                                        @if ($row['finalized'])<i class="fa-solid fa-lock small text-secondary" aria-hidden="true"></i>@endif
                                    @else
                                        <span class="small text-secondary">{{ __('exams.incomplete') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if (! $print && $open->isNotEmpty())
                <div class="att-savebar">
                    <span class="small text-secondary">{{ __('exams.reexam_sheet_hint') }}</span>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('exams.save_marks') }}</button>
                </div>
            @endif
        </form>

        @if (! $print && $open->isNotEmpty())
            <form method="POST" action="{{ route('exams.reexam.finalize', $section) }}" class="mt-3 text-end" data-confirm="{{ __('exams.confirm_reexam_finalize') }}">
                @csrf
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('exams.reexam_finalize') }}</button>
            </form>
        @endif

        @if ($print)
            <div class="signatures">
                <div>{{ __('app.signature_teacher') }}</div>
                <div>{{ __('app.signature_principal') }}</div>
            </div>
        @elseif ($reexamFinalized)
            <x-modal-form id="reexamUnfinalizeModal" :title="__('exams.reexam_unfinalize')" :action="route('exams.reexam.unfinalize', $section)" :submit="__('exams.reexam_unfinalize')" submit-class="btn-danger">
                <p class="small text-secondary">{{ __('exams.reexam_unfinalize_hint') }}</p>
                <x-form.textarea name="reason" :label="__('students.reason')" rows="2" required maxlength="500" />
            </x-modal-form>
        @endif
    @endif
@endsection
