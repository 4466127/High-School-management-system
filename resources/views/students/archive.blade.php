@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('archive.title')])

@php($query = array_filter($filters))

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('archive.title') }}</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('archive.title') }}</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('student-archive.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('past_results.former_add') }}</a>
                <a href="{{ route('student-archive.index', $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('student-archive.export', $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('archive.hint') }}</div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><label for="a-q" class="form-label">{{ __('app.search') }}</label><input type="search" id="a-q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('students.search_placeholder') }}"></div>
                <div class="col-md-3">
                    <label for="a-status" class="form-label">{{ __('students.status') }}</label>
                    <select name="status" id="a-status" class="form-select">
                        <option value="">{{ __('archive.all_statuses') }}</option>
                        @foreach (\App\Http\Controllers\Students\ArchiveController::STATUSES as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="a-year" class="form-label">{{ __('archive.year') }}</label>
                    <select name="year" id="a-year" class="form-select">
                        <option value="">{{ __('archive.any_year') }}</option>
                        @foreach ($years as $y)<option value="{{ $y->id }}" @selected($filters['year'] === $y->id)>@fa($y->name)</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
            </div>
        </form>
    @endif

    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                <thead><tr>
                    <th>{{ __('students.admission_no') }}</th><th>{{ __('students.full_name') }}</th><th>{{ __('students.father_name') }}</th>
                    <th>{{ __('students.status') }}</th><th>{{ __('archive.last_year') }}</th><th>{{ __('archive.last_result') }}</th>
                    <th>{{ __('archive.left_on') }}</th><th>{{ __('archive.certificate_no') }}</th>@unless ($print)<th></th>@endunless
                </tr></thead>
                <tbody>
                @forelse ($students as $s)
                    @php($last = $s->enrollments->first())
                    @php($past = $last ? null : $s->pastResults->last())
                    <tr>
                        <td>@fa($s->admission_no)</td>
                        <td>@if ($print){{ $s->full_name }}@else<a href="{{ route('students.show', $s) }}">{{ $s->full_name }}</a>@endif</td>
                        <td>{{ $s->father_name }}</td>
                        <td><span class="badge {{ $s->status->badgeClass() }}">{{ $s->status->label() }}</span></td>
                        @if ($past)
                            <td class="small">@fa($past->year_name) — {{ $past->grade_name }}</td>
                            <td class="small">{{ $past->result?->label() ?? '—' }}@if ($past->average !== null) ({{ \App\Support\Digits::toPersian(\App\Services\MarkService::format($past->average)) }})@endif</td>
                        @else
                            <td class="small">@if ($last)@fa($last->academicYear->name) — {{ $last->section->gradeLevel->name }}@else — @endif</td>
                            <td class="small">{{ $last?->final_result?->label() ?? '—' }}@if ($last?->final_average !== null) ({{ \App\Support\Digits::toPersian(\App\Services\MarkService::format($last->final_average)) }})@endif</td>
                        @endif
                        <td class="small">{{ $s->status_changed_on ? \App\Support\JalaliDate::format($s->status_changed_on) : '—' }}</td>
                        <td class="small">{{ $s->certificate_no ? \App\Support\Digits::toPersian($s->certificate_no) : '—' }}</td>
                        @unless ($print)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('exams.transcripts.student', $s) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('transcripts.print') }}"><i class="fa-solid fa-scroll" aria-hidden="true"></i></a>
                                <a href="{{ route('student-transfers.record', $s) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('students.record_print') }}"><i class="fa-solid fa-print" aria-hidden="true"></i></a>
                                @if ($s->status === \App\Enums\StudentStatus::Graduated)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cert{{ $s->id }}">{{ __('archive.certificate_edit') }}</button>
                                @endif
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-4">{{ __('archive.none') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @unless ($print)
        <div class="mt-3">{{ $students->links() }}</div>
        @foreach ($students->filter(fn ($s) => $s->status === \App\Enums\StudentStatus::Graduated) as $s)
            @php($open = old('_modal') === 'cert'.$s->id)
            <x-modal-form :id="'cert'.$s->id" :title="__('archive.certificate_edit').' — '.$s->full_name" :action="route('student-archive.certificate', $s)" method="PATCH" :submit="__('app.save')">
                <x-form.input :id="'cn'.$s->id" name="certificate_no" :label="__('archive.certificate_no')" :value="$s->certificate_no" maxlength="100" :use-old="$open" />
                <x-jalali-date :id="'cd'.$s->id" name="certificate_date" :label="__('archive.certificate_date')" :value="$s->certificate_date" :use-old="$open" />
                <x-form.input :id="'cnote'.$s->id" name="certificate_note" :label="__('archive.certificate_note')" :value="$s->certificate_note" maxlength="500" :use-old="$open" />
            </x-modal-form>
        @endforeach
    @endunless
@endsection
