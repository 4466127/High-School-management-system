@extends('layouts.app', ['title' => __('library.borrower_title', ['name' => $borrower->full_name])])

@php
    $t = 'library.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $isStudent = $type === \App\Enums\BorrowerType::Student;
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">
            <i class="fa-solid {{ $isStudent ? 'fa-user-graduate' : 'fa-chalkboard-user' }} text-secondary" aria-hidden="true"></i>
            {{ $borrower->full_name }}
            <span class="text-secondary fs-6">— {{ $type->label() }}</span>
        </h1>
        <a href="{{ route('library.loans.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <div class="card card-body mb-3 small">
        <div class="d-flex flex-wrap gap-4">
            @if ($isStudent)
                <span><span class="text-secondary">{{ __('students.admission_no') }}:</span> @fa($borrower->admission_no)</span>
                <span><span class="text-secondary">{{ __($t.'father') }}:</span> {{ $borrower->father_name }}</span>
                <span><span class="text-secondary">{{ __('academics.section') }}:</span> {{ $section?->label ?? '—' }}</span>
                <span><span class="text-secondary">{{ __('students.status') }}:</span> {{ $borrower->status->label() }}</span>
            @else
                <span><span class="text-secondary">{{ __($t.'position') }}:</span> {{ $borrower->position ?: $borrower->staff_type?->label() }}</span>
                <span><span class="text-secondary">{{ __('students.status') }}:</span> {{ $borrower->employment_status->label() }}</span>
            @endif
            <span><span class="text-secondary">{{ __($t.'loans_now') }}:</span> {{ __($t.'of_limit', ['count' => $n($loanCount), 'max' => $n($limit)]) }}</span>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- امانت تازه --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-medium"><i class="fa-solid fa-hand-holding" aria-hidden="true"></i> {{ __($t.'new_loan') }}</div>
                <div class="card-body">
                    @if (! $canBorrow)
                        <div class="alert alert-warning small mb-0">{{ __($t.'borrower_inactive') }}</div>
                    @else
                        <form method="POST" action="{{ $storeRoute }}" novalidate>
                            @csrf
                            <x-form.input name="accession_no" :label="__($t.'accession_no')" required maxlength="30" autofocus :placeholder="__($t.'ph.accession_no')" :help="__($t.'help.accession_no')" />
                            <x-jalali-date name="due_on" :label="__($t.'due_on')" :placeholder="$defaultDue" :help="__($t.'help.due_on', ['date' => $defaultDue])" />
                            <x-form.input name="notes" :label="__($t.'loan_notes')" maxlength="300" :placeholder="__($t.'ph.loan_notes')" />
                            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __($t.'do_lend') }}</button>
                        </form>
                        <div class="form-text mt-2">{{ __($t.'rules_line', [
                            'days' => $n($isStudent ? $rules['days_student'] : $rules['days_staff']), 'max' => $n($limit), 'renewals' => $n($rules['max_renewals']),
                        ]) }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- امانت‌های فعلی --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header fw-medium"><i class="fa-solid fa-book-open-reader" aria-hidden="true"></i> {{ __($t.'current_loans') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'title') }}</th><th>{{ __($t.'loaned_on') }}</th><th>{{ __($t.'due_on') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($active as $l)
                            <tr @class(['loan-overdue' => $l->isOverdue()])>
                                <td class="fw-medium">@fa($l->copy->accession_no)</td>
                                <td>{{ $l->copy->book->title }}@if ($l->kind === \App\Enums\LoanKind::Textbook) <span class="badge text-bg-light border">{{ $l->kind->label() }}</span>@endif</td>
                                <td class="small">{{ \App\Support\JalaliDate::format($l->loaned_on) }}</td>
                                <td class="small">{{ \App\Support\JalaliDate::format($l->due_on) }}
                                    @if ($l->isOverdue())<div><span class="badge text-bg-danger">{{ __($t.'late_days', ['days' => $n($l->overdueDays())]) }}</span></div>@endif</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#return{{ $l->id }}">{{ __($t.'do_return') }}</button>
                                    @if ($l->kind === \App\Enums\LoanKind::Loan && $l->renewals < $rules['max_renewals'])
                                        <form method="POST" action="{{ route('library.loans.renew', $l) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary">{{ __($t.'renew') }}</button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#lost{{ $l->id }}">{{ __($t.'mark_lost') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">{{ __($t.'no_current_loans') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($history->isNotEmpty())
        <div class="card">
            <div class="card-header fw-medium"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> {{ __($t.'borrower_history') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'title') }}</th><th>{{ __($t.'loaned_on') }}</th><th>{{ __($t.'returned_on') }}</th><th>{{ __($t.'status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($history as $l)
                        <tr>
                            <td>@fa($l->copy->accession_no)</td>
                            <td>{{ $l->copy->book->title }}</td>
                            <td class="small">{{ \App\Support\JalaliDate::format($l->loaned_on) }}</td>
                            <td class="small">{{ $l->returned_on ? \App\Support\JalaliDate::format($l->returned_on) : '—' }}@if ($l->overdueDays()) <span class="text-danger">({{ __($t.'late_days', ['days' => $n($l->overdueDays())]) }})</span>@endif</td>
                            <td><span class="badge {{ $l->status->badgeClass() }}">{{ $l->status->label() }}</span>@if ($l->returnConditionLabel()) <span class="small text-secondary">{{ $l->returnConditionLabel() }}</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- پنجره‌ها --}}
    @foreach ($active as $l)
        @php($rid = 'return'.$l->id)
        <x-modal-form :id="$rid" :title="__($t.'do_return').' — '.$l->copy->book->title" :action="route('library.loans.return', $l)" :submit="__($t.'do_return')" submit-class="btn-success">
            <div class="mb-3">
                <label class="form-label" for="{{ $rid }}-cond">{{ __($t.'return_condition') }}</label>
                <select name="condition" id="{{ $rid }}-cond" class="form-select">
                    <option value="">{{ __($t.'condition_unchanged') }}</option>
                    @foreach ($conditions as $v => $l2)<option value="{{ $v }}">{{ $l2 }}</option>@endforeach
                </select>
                <div class="form-text">{{ __($t.'help.return_condition') }}</div>
            </div>
            <x-form.input :id="$rid.'-note'" name="note" :label="__($t.'item_note')" maxlength="300" :use-old="old('_modal') === $rid" :placeholder="__($t.'ph.return_note')" />
        </x-modal-form>

        @php($lid = 'lost'.$l->id)
        <x-modal-form :id="$lid" :title="__($t.'mark_lost').' — '.$l->copy->book->title" :action="route('library.loans.lost', $l)" :submit="__($t.'mark_lost')" submit-class="btn-danger">
            <p class="small text-secondary">{{ __($t.'lost_hint') }}</p>
            <x-form.input :id="$lid.'-note'" name="note" :label="__($t.'item_note')" required maxlength="300" :use-old="old('_modal') === $lid" :placeholder="__($t.'ph.lost_note')" />
        </x-modal-form>
    @endforeach
@endsection
