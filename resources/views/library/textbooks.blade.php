@extends('layouts.app', ['title' => __('library.textbooks_title')])

@php
    $t = 'library.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $withLoan = $rows->filter(fn ($r) => $r['loan']);
    $grade = $section?->gradeLevel?->name;
@endphp

@section('content')
    <h1 class="page-title">{{ __($t.'textbooks_title') }}@if ($year) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($year->name)</span>@endif</h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'textbooks_intro') }}</div>

    @if (! $year)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @else
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="tb-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="tb-section" class="form-select" required data-submit-on-change data-reset="#tb-book">
                        <option value="">{{ __($t.'choose_section') }}</option>
                        @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label }}</option>@endforeach
                    </select>
                    <div class="form-text">{{ __($t.'help.textbook_section') }}</div>
                </div>
                @if ($section)
                    <div class="col-md-5">
                        <label for="tb-book" class="form-label">{{ __($t.'textbook') }}</label>
                        <select name="book" id="tb-book" class="form-select" data-submit-on-change>
                            <option value="">{{ __($t.'all_grade_textbooks', ['grade' => $grade]) }}</option>
                            @if ($books->isNotEmpty())
                                <optgroup label="{{ __($t.'grade_textbooks', ['grade' => $grade]) }}">
                                    @foreach ($books as $b)<option value="{{ $b->id }}" @selected($book?->id === $b->id)>{{ $b->title }} — {{ __($t.'available_n', ['count' => $n($b->copies_available)]) }}</option>@endforeach
                                </optgroup>
                            @endif
                            @if ($ungraded->isNotEmpty())
                                <optgroup label="{{ __($t.'ungraded_textbooks') }}">
                                    @foreach ($ungraded as $b)<option value="{{ $b->id }}" @selected($book?->id === $b->id)>{{ $b->title }} — {{ __($t.'available_n', ['count' => $n($b->copies_available)]) }}</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                @endif
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __($t.'show') }}</button></div>
            </div>
        </form>

        @if ($section && ! $book)
            {{-- خلاصه‌ی کتاب‌های صنف: توزیع همه (اول سال) و جمع‌آوری همه (آخر سال) --}}
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header fw-medium"><i class="fa-solid fa-book" aria-hidden="true"></i> {{ __($t.'grade_textbooks', ['grade' => $grade]) }} — {{ $section->label }}</div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>{{ __($t.'title') }}</th><th>{{ __($t.'subject') }}</th><th>{{ __($t.'copies_available_short') }}</th><th>{{ __($t.'students_have') }}</th><th></th></tr></thead>
                                <tbody>
                                @forelse ($books as $b)
                                    @php($have = (int) ($haveByBook[$b->id] ?? 0))
                                    <tr>
                                        <td class="fw-medium">{{ $b->title }}</td>
                                        <td class="small">{{ $b->subject?->name ?? '—' }}</td>
                                        <td>@fa($b->copies_available)</td>
                                        <td><span @class(['badge', 'text-bg-success' => $studentCount > 0 && $have >= $studentCount, 'text-bg-light border' => $have < $studentCount || $studentCount === 0])>{{ __($t.'of_limit', ['count' => $n($have), 'max' => $n($studentCount)]) }}</span></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('library.textbooks.index', ['section' => $section->id, 'book' => $b->id]) }}">{{ __($t.'students_list') }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">{{ __($t.'no_grade_textbooks', ['grade' => $grade]) }}
                                        <div class="small mt-1">{{ __($t.'no_grade_textbooks_hint') }}</div></td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($ungraded->isNotEmpty())
                        <div class="alert alert-warning small mt-3 mb-0">{{ __($t.'ungraded_warning', ['count' => $n($ungraded->count())]) }}</div>
                    @endif
                </div>
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header fw-medium"><i class="fa-solid fa-share-from-square" aria-hidden="true"></i> {{ __($t.'distribute_all') }}</div>
                        <div class="card-body">
                            <p class="small">{{ __($t.'distribute_all_hint', ['books' => $n($books->count()), 'students' => $n($studentCount)]) }}</p>
                            <form method="POST" action="{{ route('library.textbooks.distribute-all') }}" novalidate data-confirm="{{ __($t.'distribute_all_confirm') }}">
                                @csrf
                                <input type="hidden" name="section" value="{{ $section->id }}">
                                <x-jalali-date id="tb-all-due" name="due_on" :label="__($t.'textbook_due')" :value="$defaultDue" required :help="__($t.'help.textbook_due')" />
                                <button type="submit" class="btn btn-primary w-100" @disabled($books->isEmpty() || $studentCount === 0)><i class="fa-solid fa-share-from-square" aria-hidden="true"></i> {{ __($t.'distribute_all_button') }}</button>
                            </form>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header fw-medium"><i class="fa-solid fa-inbox" aria-hidden="true"></i> {{ __($t.'collect_all') }}</div>
                        <div class="card-body">
                            <p class="small">{{ __($t.'collect_all_hint') }}</p>
                            <form method="POST" action="{{ route('library.textbooks.collect-all') }}" data-confirm="{{ __($t.'collect_all_confirm') }}">
                                @csrf
                                <input type="hidden" name="section" value="{{ $section->id }}">
                                <div class="mb-3">
                                    <label class="form-label" for="tb-all-cond">{{ __($t.'return_condition') }}</label>
                                    <select name="condition" id="tb-all-cond" class="form-select">
                                        <option value="">{{ __($t.'condition_unchanged') }}</option>
                                        @foreach ($conditions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100" @disabled($haveByBook->sum() === 0)><i class="fa-solid fa-inbox" aria-hidden="true"></i> {{ __($t.'collect_all_button') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($section && $book)
            <div class="mb-2"><a href="{{ route('library.textbooks.index', ['section' => $section->id]) }}" class="small"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> {{ __($t.'back_to_grade_books', ['grade' => $grade]) }}</a></div>
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header fw-medium"><i class="fa-solid fa-share-from-square" aria-hidden="true"></i> {{ __($t.'distribute') }}</div>
                        <div class="card-body">
                            <p class="small">{{ __($t.'distribute_summary', ['students' => $n($rows->count()), 'have' => $n($withLoan->count()), 'available' => $n($available)]) }}</p>
                            @if ($rows->count() - $withLoan->count() > $available)
                                <div class="alert alert-warning small">{{ __($t.'not_enough_copies', ['count' => $n($rows->count() - $withLoan->count() - $available)]) }}</div>
                            @endif
                            <form method="POST" action="{{ route('library.textbooks.distribute') }}" novalidate>
                                @csrf
                                <input type="hidden" name="section" value="{{ $section->id }}">
                                <input type="hidden" name="book" value="{{ $book->id }}">
                                <x-jalali-date name="due_on" :label="__($t.'textbook_due')" :value="$defaultDue" required :help="__($t.'help.textbook_due')" />
                                <button type="submit" class="btn btn-primary w-100" @disabled($rows->count() === $withLoan->count() || $available === 0)><i class="fa-solid fa-share-from-square" aria-hidden="true"></i> {{ __($t.'distribute_button') }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <form method="POST" action="{{ route('library.textbooks.collect') }}" class="card" data-confirm="{{ __($t.'collect_confirm') }}">
                        @csrf
                        <div class="card-header d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-medium flex-grow-1"><i class="fa-solid fa-users" aria-hidden="true"></i> {{ $section->label }} — {{ $book->title }}</span>
                            @if ($withLoan->isNotEmpty())
                                <select name="condition" class="form-select form-select-sm w-auto" aria-label="{{ __($t.'return_condition') }}">
                                    <option value="">{{ __($t.'condition_unchanged') }}</option>
                                    @foreach ($conditions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-inbox" aria-hidden="true"></i> {{ __($t.'collect_button') }}</button>
                            @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr>
                                    <th style="width: 3rem">@if ($withLoan->isNotEmpty())<button type="button" class="btn btn-sm btn-link p-0" data-check-all="loans[]">{{ __('app.all') }}</button>@endif</th>
                                    <th>#</th><th>{{ __('students.full_name') }}</th><th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'loaned_on') }}</th>
                                </tr></thead>
                                <tbody>
                                @forelse ($rows as $r)
                                    <tr>
                                        <td>@if ($r['loan'])<input type="checkbox" class="form-check-input" name="loans[]" value="{{ $r['loan']->id }}" aria-label="{{ $r['enrollment']->student->full_name }}">@endif</td>
                                        <td class="small">@fa($r['enrollment']->roll_no ?? '')</td>
                                        <td>{{ $r['enrollment']->student->full_name }} <span class="small text-secondary">{{ __($t.'child_of') }} {{ $r['enrollment']->student->father_name }}</span></td>
                                        <td>@if ($r['loan'])<span class="fw-medium">@fa($r['loan']->copy->accession_no)</span>@else<span class="small text-secondary">{{ __($t.'not_given') }}</span>@endif</td>
                                        <td class="small">{{ $r['loan'] ? \App\Support\JalaliDate::format($r['loan']->loaned_on) : '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">{{ __($t.'no_students_section') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
@endsection
