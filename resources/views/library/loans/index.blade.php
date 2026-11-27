@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('library.loans_title')])

@php
    $t = 'library.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $status = $filters['status'] ?? 'active';
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __($t.'loans_title') }} — {{ __($t.'loan_filters.'.$status) }}</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __($t.'loans_title') }}</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('library.loans.index', array_merge(request()->query(), ['print' => 1])) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('app.print') }}</a>
                <a href="{{ route('library.loans.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'loans_intro') }}</div>

        <div class="row g-3 mb-3">
            {{-- امانت دادن: پیدا کردن امانت‌گیرنده --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header fw-medium"><i class="fa-solid fa-hand-holding" aria-hidden="true"></i> {{ __($t.'lend_title') }}</div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('library.loans.lookup') }}" class="row g-2 align-items-end">
                            <div class="col">
                                <label for="find" class="form-label">{{ __($t.'find_borrower') }}</label>
                                <input type="search" name="find" id="find" class="form-control" value="{{ $find }}" placeholder="{{ __($t.'ph.find_borrower') }}" required autofocus>
                                <div class="form-text">{{ __($t.'help.find_borrower') }}</div>
                            </div>
                            <div class="col-auto mb-4"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('app.search') }}</button></div>
                        </form>

                        @if ($matches !== null)
                            @if ($matches['students']->isEmpty() && $matches['staff']->isEmpty())
                                <div class="alert alert-warning small mb-0">{{ __($t.'no_borrower_found') }}</div>
                            @else
                                <div class="list-group small">
                                    @foreach ($matches['students'] as $s)
                                        <a href="{{ route('library.borrowers.student', $s) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                                            <i class="fa-solid fa-user-graduate text-secondary" aria-hidden="true"></i>
                                            <span class="flex-grow-1"><span class="fw-medium">{{ $s->full_name }}</span> <span class="text-secondary">{{ __($t.'child_of') }} {{ $s->father_name }}</span></span>
                                            <span class="text-secondary">@fa($s->admission_no)@if ($s->currentEnrollment) · {{ $s->currentEnrollment->section->gradeLevel->name }} {{ $s->currentEnrollment->section->name }}@endif</span>
                                        </a>
                                    @endforeach
                                    @foreach ($matches['staff'] as $s)
                                        <a href="{{ route('library.borrowers.staff', $s) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                                            <i class="fa-solid fa-chalkboard-user text-secondary" aria-hidden="true"></i>
                                            <span class="flex-grow-1 fw-medium">{{ $s->full_name }}</span>
                                            <span class="text-secondary">{{ $s->position ?: $s->staff_type?->label() }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- بازگشت سریع --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header fw-medium"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> {{ __($t.'quick_return') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('library.loans.quick-return') }}" novalidate>
                            @csrf
                            <x-form.input name="return_accession" :label="__($t.'accession_no')" required maxlength="30" :placeholder="__($t.'ph.accession_no')" :help="__($t.'help.quick_return')" />
                            <div class="row g-2 align-items-end">
                                <div class="col">
                                    <label class="form-label" for="qr-cond">{{ __($t.'return_condition') }}</label>
                                    <select name="condition" id="qr-cond" class="form-select">
                                        <option value="">{{ __($t.'condition_unchanged') }}</option>
                                        @foreach ($conditions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-auto"><button type="submit" class="btn btn-success"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __($t.'do_return') }}</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach (['active' => 'text-bg-primary', 'overdue' => 'text-bg-danger', 'due_today' => 'text-bg-warning'] as $key => $badge)
                <a href="{{ route('library.loans.index', ['status' => $key === 'due_today' ? 'due_soon' : $key]) }}" class="badge {{ $badge }} text-decoration-none fs-6 fw-normal">{{ __($t.'count_'.$key, ['count' => $n($counts[$key])]) }}</a>
            @endforeach
        </div>

        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="ln-status" class="form-label">{{ __($t.'status') }}</label>
                    <select name="status" id="ln-status" class="form-select">
                        @foreach (__($t.'loan_filters') as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="ln-type" class="form-label">{{ __($t.'borrower') }}</label>
                    <select name="type" id="ln-type" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (\App\Enums\BorrowerType::options() as $v => $l)<option value="{{ $v }}" @selected(($filters['type'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="ln-kind" class="form-label">{{ __($t.'kind') }}</label>
                    <select name="kind" id="ln-kind" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (\App\Enums\LoanKind::options() as $v => $l)<option value="{{ $v }}" @selected(($filters['kind'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="ln-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="ln-section" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach ($sections as $s)<option value="{{ $s->id }}" @selected((int) ($filters['section'] ?? 0) === $s->id)>{{ $s->label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ln-q" class="form-label">{{ __('app.search') }}</label>
                    <input type="search" name="q" id="ln-q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __($t.'ph.loan_search') }}">
                </div>
                <div class="col-md-1"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
            </div>
        </form>
    @endif

    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                <thead><tr>
                    <th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'title') }}</th><th>{{ __($t.'borrower') }}</th>
                    <th>{{ __($t.'loaned_on') }}</th><th>{{ __($t.'due_on') }}</th><th>{{ __($t.'status') }}</th>
                    @unless ($print)<th></th>@endunless
                </tr></thead>
                <tbody>
                @forelse ($loans as $l)
                    @php($late = $l->overdueDays())
                    <tr @class(['loan-overdue' => $l->isOverdue()])>
                        <td class="fw-medium">@fa($l->copy->accession_no)</td>
                        <td>{{ $l->copy->book->title }}@if ($l->kind === \App\Enums\LoanKind::Textbook) <span class="badge text-bg-light border">{{ $l->kind->label() }}</span>@endif</td>
                        <td>
                            @if ($print){{ $l->borrowerName() }}
                            @else<a href="{{ $l->student_id ? route('library.borrowers.student', $l->student_id) : route('library.borrowers.staff', $l->staff_id) }}">{{ $l->borrowerName() }}</a>@endif
                            <div class="small text-secondary">{{ \App\Http\Controllers\Library\LoanController::borrowerDetail($l) ?: $l->borrower_type->label() }}</div>
                        </td>
                        <td class="small">{{ \App\Support\JalaliDate::format($l->loaned_on) }}</td>
                        <td class="small">{{ \App\Support\JalaliDate::format($l->due_on) }}@if ($l->renewals) <span class="text-secondary">({{ __($t.'renewed_n', ['count' => $n($l->renewals)]) }})</span>@endif</td>
                        <td>
                            @if ($l->isOverdue())<span class="badge text-bg-danger">{{ __($t.'late_days', ['days' => $n($late)]) }}</span>
                            @else<span class="badge {{ $l->status->badgeClass() }}">{{ $l->status->label() }}</span>
                                @if ($l->returned_on)<div class="small text-secondary">{{ \App\Support\JalaliDate::format($l->returned_on) }}@if ($late) · {{ __($t.'late_days', ['days' => $n($late)]) }}@endif</div>@endif
                            @endif
                        </td>
                        @unless ($print)
                            <td class="text-end text-nowrap">
                                @if ($l->status === \App\Enums\LoanStatus::Active)
                                    <form method="POST" action="{{ route('library.loans.return', $l) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">{{ __($t.'do_return') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">{{ __($t.'no_loans') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @unless ($print)<div class="mt-3">{{ $loans->links() }}</div>@endunless
@endsection
