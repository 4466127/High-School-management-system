{{-- زبانه‌ی کتابخانه در پرونده‌ی دانش‌آموز (مرحله‌ی ۱۷) --}}
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="fw-medium flex-grow-1"><i class="fa-solid fa-book" aria-hidden="true"></i> {{ __('library.student_loans') }}</span>
        @if (auth()->user()->hasPermission('library.manage'))
            <a href="{{ route('library.borrowers.student', $student) }}" class="btn btn-sm btn-outline-primary">{{ __('library.open_borrower') }}</a>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('library.accession_no') }}</th><th>{{ __('library.title') }}</th><th>{{ __('library.loaned_on') }}</th><th>{{ __('library.due_on') }}</th><th>{{ __('library.status') }}</th></tr></thead>
            <tbody>
            @foreach ($libraryLoans as $l)
                <tr @class(['loan-overdue' => $l->isOverdue()])>
                    <td>@fa($l->copy->accession_no)</td>
                    <td>{{ $l->copy->book->title }}@if ($l->kind === \App\Enums\LoanKind::Textbook) <span class="badge text-bg-light border">{{ $l->kind->label() }}</span>@endif</td>
                    <td class="small">@jdate($l->loaned_on)</td>
                    <td class="small">@jdate($l->due_on)</td>
                    <td>
                        @if ($l->isOverdue())<span class="badge text-bg-danger">{{ __('library.late_days', ['days' => \App\Support\Digits::toPersian((string) $l->overdueDays())]) }}</span>
                        @else<span class="badge {{ $l->status->badgeClass() }}">{{ $l->status->label() }}</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
