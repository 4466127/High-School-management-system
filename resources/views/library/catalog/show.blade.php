@extends('layouts.app', ['title' => $book->title])

@php
    $t = 'library.';
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $active = $book->copies->where('status', '!=', \App\Enums\CopyStatus::Withdrawn);
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $book->title }} @if ($book->is_reference)<span class="badge text-bg-light border fs-6">{{ __($t.'reference_badge') }}</span>@endif</h1>
        <div class="d-flex flex-wrap gap-2">
            @if ($canManage)
                <a href="{{ route('library.catalog.edit', $book) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                @if ($active->isNotEmpty())
                    <a href="{{ route('library.catalog.labels', $book) }}" class="btn btn-outline-secondary" target="_blank"><i class="fa-solid fa-tags" aria-hidden="true"></i> {{ __($t.'print_labels') }}</a>
                @endif
                @if ($book->copies->isEmpty())
                    <form method="POST" action="{{ route('library.catalog.destroy', $book) }}" data-confirm="{{ __($t.'delete_book_confirm') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash" aria-hidden="true"></i> {{ __('app.delete') }}</button>
                    </form>
                @endif
            @endif
            <a href="{{ route('library.catalog.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-medium"><i class="fa-solid fa-book" aria-hidden="true"></i> {{ __($t.'book_info') }}</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        @foreach ([
                            'author' => $book->author, 'translator' => $book->translator, 'publisher' => $book->publisher,
                            'publish_year' => $book->publish_year ? $n($book->publish_year) : null, 'edition' => $book->edition,
                            'isbn' => $book->isbn, 'category' => $book->category->label(), 'language' => $book->language->label(),
                            'grade' => $book->gradeLevel?->name, 'subject' => $book->subject?->name,
                            'call_number' => $book->call_number ? $n($book->call_number) : null, 'pages' => $book->pages ? $n($book->pages) : null,
                            'keywords' => $book->keywords, 'notes' => $book->notes,
                        ] as $key => $value)
                            @if ($value !== null && $value !== '')
                                <dt class="col-5 text-secondary fw-normal">{{ __($t.$key) }}</dt>
                                <dd class="col-7" @if ($key === 'isbn') dir="ltr" style="text-align: right" @endif>{{ $value }}</dd>
                            @endif
                        @endforeach
                        <dt class="col-5 text-secondary fw-normal">{{ __($t.'times_borrowed') }}</dt>
                        <dd class="col-7">{{ $n($timesBorrowed) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="fw-medium flex-grow-1"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ __($t.'copies_title') }}
                        <span class="small text-secondary">— {{ __($t.'copies_summary', ['total' => $n($active->count()), 'available' => $n($active->where('status', \App\Enums\CopyStatus::Available)->count())]) }}</span></span>
                    @if ($canManage)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#copyNew"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($t.'add_copies') }}</button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'status') }}</th><th>{{ __($t.'condition') }}</th>
                            <th>{{ __($t.'source') }}</th>@if ($canManage)<th></th>@endif
                        </tr></thead>
                        <tbody>
                        @forelse ($book->copies as $c)
                            <tr @class(['text-secondary' => $c->status === \App\Enums\CopyStatus::Withdrawn])>
                                <td class="fw-medium">@fa($c->accession_no)</td>
                                <td>
                                    <span class="badge {{ $c->status->badgeClass() }}">{{ $c->status->label() }}</span>
                                    @if ($c->activeLoan)
                                        <div class="small">
                                            @if ($canManage)
                                                <a href="{{ $c->activeLoan->student_id ? route('library.borrowers.student', $c->activeLoan->student_id) : route('library.borrowers.staff', $c->activeLoan->staff_id) }}">{{ $c->activeLoan->borrowerName() }}</a>
                                            @endif
                                            <span @class(['text-danger' => $c->activeLoan->isOverdue(), 'text-secondary' => ! $c->activeLoan->isOverdue()])>· {{ __($t.'due') }} {{ \App\Support\JalaliDate::format($c->activeLoan->due_on) }}</span>
                                        </div>
                                    @elseif ($c->status_reason)
                                        <div class="small text-secondary">{{ $c->status_reason }}</div>
                                    @endif
                                </td>
                                <td class="small">{{ $c->condition->label() }}</td>
                                <td class="small">{{ $c->source->label() }}@if ($c->acquired_on)<div class="text-secondary">{{ \App\Support\JalaliDate::format($c->acquired_on) }}</div>@endif
                                    @if ($canManage && $c->price !== null)<div class="text-secondary">{{ \App\Support\Money::format($c->price) }}</div>@endif</td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#copy{{ $c->id }}" aria-label="{{ __('app.edit') }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                        @if ($c->status !== \App\Enums\CopyStatus::OnLoan)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#status{{ $c->id }}">{{ __($t.'change_status') }}</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-3">{{ __($t.'no_copies') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($canManage && $history->isNotEmpty())
        <div class="card">
            <div class="card-header fw-medium"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> {{ __($t.'loan_history') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __($t.'accession_no') }}</th><th>{{ __($t.'borrower') }}</th><th>{{ __($t.'loaned_on') }}</th><th>{{ __($t.'due_on') }}</th><th>{{ __($t.'returned_on') }}</th><th>{{ __($t.'status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($history as $l)
                        <tr>
                            <td>@fa($l->copy->accession_no)</td>
                            <td>{{ $l->borrowerName() }} <span class="small text-secondary">({{ $l->borrower_type->label() }}@if ($l->kind === \App\Enums\LoanKind::Textbook) · {{ $l->kind->label() }}@endif)</span></td>
                            <td class="small">{{ \App\Support\JalaliDate::format($l->loaned_on) }}</td>
                            <td class="small">{{ \App\Support\JalaliDate::format($l->due_on) }}</td>
                            <td class="small">{{ $l->returned_on ? \App\Support\JalaliDate::format($l->returned_on) : '—' }}</td>
                            <td><span class="badge {{ $l->isOverdue() ? 'text-bg-danger' : $l->status->badgeClass() }}">{{ $l->isOverdue() ? __($t.'overdue') : $l->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- پنجره‌ها --}}
    @if ($canManage)
        @php($open = old('_modal') === 'copyNew')
        <x-modal-form id="copyNew" :title="__($t.'add_copies').' — '.$book->title" :action="route('library.copies.store', $book)" :submit="__('app.save')" size="lg">
            <p class="small text-secondary">{{ __($t.'add_copies_hint') }}</p>
            @include('library.catalog._copy-fields', ['prefix' => 'copyNew', 'withCount' => true, 'open' => $open])
        </x-modal-form>

        @foreach ($book->copies as $c)
            @php($id = 'copy'.$c->id)
            <x-modal-form :id="$id" :title="__($t.'edit_copy').' — '.\App\Support\Digits::toPersian($c->accession_no)" :action="route('library.copies.update', $c)" method="PUT" :submit="__('app.save')" size="lg">
                @include('library.catalog._copy-fields', ['prefix' => $id, 'copy' => $c, 'open' => old('_modal') === $id])
            </x-modal-form>

            @if ($c->status !== \App\Enums\CopyStatus::OnLoan)
                @php($sid = 'status'.$c->id)
                @php($sopen = old('_modal') === $sid)
                <x-modal-form :id="$sid" :title="__($t.'change_status').' — '.\App\Support\Digits::toPersian($c->accession_no)" :action="route('library.copies.status', $c)" :submit="__('app.save')">
                    <div class="mb-3">
                        <label class="form-label" for="{{ $sid }}-st">{{ __($t.'new_status') }}</label>
                        <select name="status" id="{{ $sid }}-st" class="form-select">
                            @foreach ([\App\Enums\CopyStatus::Available, \App\Enums\CopyStatus::Damaged, \App\Enums\CopyStatus::Lost, \App\Enums\CopyStatus::Withdrawn] as $st)
                                @continue($st === $c->status)
                                <option value="{{ $st->value }}" @selected($sopen && old('status') === $st->value)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form.input :id="$sid.'-reason'" name="reason" :label="__($t.'reason')" maxlength="300" :use-old="$sopen" :placeholder="__($t.'ph.reason')" :help="__($t.'help.reason')" />
                </x-modal-form>
            @endif
        @endforeach
    @endif
@endsection
