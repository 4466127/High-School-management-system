@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('exams.results_title')])

@php
    $fmt = fn ($n) => $n === null ? '—' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n));
    $query = array_filter(['section' => $section?->id, 'exam' => $exam?->id]);
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('exams.results_title') }} — {{ $exam?->name ?? __('exams.final_results') }}</h1>
        <div class="text-center mb-3">{{ $section?->label }} · {{ __('academics.year') }} @fa($year?->name)</div>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('exams.results_title') }}</h1>
            @if ($section)
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('exams.results.index', $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                    <a href="{{ route('exams.results.export', $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
                    <a href="{{ route('exams.reportcards.index', ['section' => $section->id]) }}" class="btn btn-outline-primary"><i class="fa-solid fa-id-card" aria-hidden="true"></i> {{ __('exams.reportcards_title') }}</a>
                </div>
            @endif
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label for="r-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="r-section" class="form-select">
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" @selected($section && $section->id === $s->id)>{{ $s->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label for="r-exam" class="form-label">{{ __('exams.view') }}</label>
                    <select name="exam" id="r-exam" class="form-select">
                        <option value="">{{ __('exams.final_results') }}</option>
                        @foreach ($exams as $e)
                            <option value="{{ $e->id }}" @selected($exam && $exam->id === $e->id)>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.view') }}</button>
                </div>
            </div>
        </form>
    @endif

    @if (! $year || ! $section)
        <div class="card card-body text-center text-secondary py-5">{{ $year ? __('attendance.no_my_sections') : __('academics.no_year') }}</div>
    @elseif ($exam)
        {{-- نمره‌های یک امتحان --}}
        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center mb-0 results-table">
                    <thead>
                    <tr>
                        <th>{{ __('academics.roll_no') }}</th>
                        <th class="text-start">{{ __('students.full_name') }}</th>
                        @foreach ($subjects as $subject)<th class="results-subject">{{ $subject->name }}</th>@endforeach
                        <th>{{ __('exams.total') }}</th>
                        <th>{{ __('exams.percent') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($examRows as $row)
                        <tr>
                            <td>@fa($row['enrollment']->roll_no)</td>
                            <td class="text-start text-nowrap">{{ $row['enrollment']->student->full_name }}</td>
                            @foreach ($subjects as $subject)
                                @php($mark = $row['marks'][$subject->id] ?? null)
                                <td @class(['text-danger fw-bold' => $mark && ! $mark->is_absent && $mark->mark !== null && (float) $mark->mark < (float) $exam->max_mark * (float) \App\Models\Setting::get('pass_percent', 40) / 100])>
                                    {{ $mark ? ($mark->is_absent ? __('exams.absent_short') : $fmt($mark->mark)) : '' }}
                                </td>
                            @endforeach
                            <td class="fw-bold">{{ $fmt($row['total']) }}</td>
                            <td>{{ $row['complete'] ? $fmt($row['percent']).'٪' : '…' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + $subjects->count() }}" class="text-secondary py-3">{{ __('academics.no_students_in_section') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="small text-secondary mt-2">{{ __('exams.exam_table_hint', ['max' => $fmt($exam->max_mark)]) }}</div>
    @else
        {{-- نتیجه‌ی نهایی --}}
        @if (! $print)
            @if ($finalized)
                <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('exams.finalized_notice') }}
                    @can('exams.manage')
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-bs-toggle="modal" data-bs-target="#unfinalizeModal">{{ __('exams.unfinalize') }}</button>
                    @endcan
                </div>
            @else
                <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 small">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    {{ __('exams.provisional_notice', ['exams' => $countedExams->pluck('name')->join('، ') ?: '—']) }}
                    @can('exams.manage')
                        @if ($rows->isNotEmpty())
                            <form method="POST" action="{{ route('exams.results.finalize', $section) }}" class="ms-auto" data-confirm="{{ __('exams.confirm_finalize') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('exams.finalize') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @endif
        @endif

        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center mb-0 results-table">
                    <thead>
                    <tr>
                        <th>{{ __('academics.roll_no') }}</th>
                        <th class="text-start">{{ __('students.full_name') }}</th>
                        @foreach ($subjects as $subject)<th class="results-subject">{{ $subject->name }}</th>@endforeach
                        <th>{{ __('exams.total') }}</th>
                        <th>{{ __('exams.average') }}</th>
                        <th>{{ __('exams.result') }}</th>
                        <th>{{ __('exams.grade') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $card)
                        @php($finals = collect($card['subjects'])->keyBy('id'))
                        @php($result = $card['result'] ? \App\Enums\FinalResult::from($card['result']) : null)
                        <tr>
                            <td>@fa($card['enrollment']->roll_no)</td>
                            <td class="text-start text-nowrap">
                                @if (! $print)
                                    <a href="{{ route('exams.reportcards.show', $card['enrollment']) }}" class="text-decoration-none">{{ $card['enrollment']->student->full_name }}</a>
                                @else
                                    {{ $card['enrollment']->student->full_name }}
                                @endif
                            </td>
                            @foreach ($subjects as $subject)
                                @php($f = $finals[$subject->id] ?? null)
                                <td @class(['text-danger fw-bold' => $f && $f['passed'] === false])>{{ $f && $f['final'] !== null ? $fmt($f['final']) : '…' }}@isset($f['reexam'])<div class="small text-success">{{ __('exams.reexam_column') }}: {{ $fmt($f['reexam']) }}</div>@endisset</td>
                            @endforeach
                            <td class="fw-bold">{{ $fmt($card['total']) }}</td>
                            <td>{{ $fmt($card['average']) }}</td>
                            <td>
                                @if ($result)
                                    <span class="badge {{ $result->badgeClass() }}">{{ $result->label() }}</span>
                                    @if (! empty($card['reexam']))<div class="small text-secondary">{{ __('exams.after_reexam') }}</div>@endif
                                @else
                                    <span class="text-secondary small">{{ __('exams.incomplete') }}</span>
                                @endif
                            </td>
                            <td class="fw-bold">{{ $card['grade'] ? __('exams.grades.'.$card['grade']) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 6 + $subjects->count() }}" class="text-secondary py-3">{{ __('academics.no_students_in_section') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="small text-secondary mt-2">{{ __('exams.final_table_hint') }}</div>

        @if ($print)
            <div class="signatures">
                <div>{{ __('app.signature_teacher') }}</div>
                <div>{{ __('app.signature_principal') }}</div>
            </div>
        @endif

        @can('exams.manage')
            @if ($finalized && ! $print)
                <x-modal-form id="unfinalizeModal" :title="__('exams.unfinalize')" :action="route('exams.results.unfinalize', $section)" :submit="__('exams.unfinalize')" submit-class="btn-danger">
                    <p class="small text-secondary">{{ __('exams.unfinalize_hint') }}</p>
                    <x-form.textarea name="reason" :label="__('students.reason')" rows="2" required maxlength="500" />
                </x-modal-form>
            @endif
        @endcan
    @endif
@endsection
