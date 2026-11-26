@extends('layouts.app', ['title' => $student->full_name])

@section('content')
    {{-- سربرگ پرونده --}}
    <div class="card profile-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <x-avatar :src="$student->photo_path ? route('students.photo', $student) : null" size="84" :alt="$student->full_name" />
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ $student->full_name }}</h1>
                <div class="text-secondary">
                    {{ __('students.father_name') }}: {{ $student->father_name }}
                    · {{ __('students.admission_no') }}: <span>@fa($student->admission_no)</span>
                </div>
                <div class="mt-1">
                    @if ($student->trashed())
                        <span class="badge text-bg-danger">{{ __('students.deleted_badge') }}</span>
                        <span class="small text-secondary">{{ __('students.deleted_reason') }}: {{ $student->deleted_reason }}</span>
                    @else
                        <span class="badge {{ $student->status->badgeClass() }}">{{ $student->status->label() }}</span>
                        @if ($student->status_reason)
                            <span class="small text-secondary">{{ $student->status_reason }} (@jdate($student->status_changed_on))</span>
                        @endif
                    @endif
                    @if ($currentEnrollment)
                        <span class="current-class ms-1"><i class="fa-solid fa-chalkboard" aria-hidden="true"></i> {{ $currentEnrollment->section->label }} · {{ __('academics.roll_no') }} @fa($currentEnrollment->roll_no)</span>
                    @elseif ($currentYear && ! $student->trashed())
                        <span class="small text-warning-emphasis ms-1">{{ __('academics.not_enrolled') }}</span>
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('update', $student)
                    <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                    @if ($student->status === \App\Enums\StudentStatus::Active)
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal">{{ __('students.deactivate') }}</button>
                    @elseif ($student->status === \App\Enums\StudentStatus::Inactive)
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#statusModal">{{ __('students.activate') }}</button>
                    @endif
                @endcan
                @can('delete', $student)
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="fa-solid fa-trash" aria-hidden="true"></i> {{ __('students.delete') }}</button>
                @endcan
                @can('restore', $student)
                    <form method="POST" action="{{ route('students.restore', $student) }}" data-confirm="{{ __('students.confirm_restore') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> {{ __('students.restore') }}</button>
                    </form>
                @endcan
                @can('letters.manage')
                    <a href="{{ route('letters.outgoing.create', ['student' => $student->admission_no]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> {{ __('letters.create_outgoing') }}</a>
                @endcan
                <a href="{{ route('students.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
    </div>

    @unless ($sensitive)
        <div class="alert alert-info small">{{ __('students.limited_view') }}</div>
    @endunless

    {{-- زبانه‌ها --}}
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info-pane" type="button" role="tab">{{ __('students.tab_info') }}</button>
        </li>
        @if ($sensitive)
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#guardians-pane" type="button" role="tab">
                    {{ __('students.tab_guardians') }} <span class="badge text-bg-light border">@fa($student->guardians->count())</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab">
                    {{ __('students.tab_documents') }} <span class="badge text-bg-light border">@fa($student->documents->count())</span>
                </button>
            </li>
        @endif
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab">{{ __('students.tab_history') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#attendance-pane" type="button" role="tab">
                {{ __('attendance.tab') }}
                @if ($attendance && $attendance['totals']['absent'] > 0)<span class="badge text-bg-danger">@fa($attendance['totals']['absent'])</span>@endif
            </button>
        </li>
        @if ($canSeeMarks)
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#marks-pane" type="button" role="tab">{{ __('exams.tab') }}</button>
            </li>
        @endif
        @if ($fees)
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#fees-pane" type="button" role="tab">
                    {{ __('finance.tab') }}
                    @if ($fees['remaining'] > 0)<span class="badge text-bg-warning">!</span>@endif
                </button>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="info-pane" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0 info-list">
                        <dt class="col-sm-3">{{ __('students.admission_no') }}</dt><dd class="col-sm-9">@fa($student->admission_no)</dd>
                        <dt class="col-sm-3">{{ __('students.first_name') }}</dt><dd class="col-sm-9">{{ $student->first_name }}</dd>
                        <dt class="col-sm-3">{{ __('students.last_name') }}</dt><dd class="col-sm-9">{{ $student->last_name ?: '—' }}</dd>
                        <dt class="col-sm-3">{{ __('students.father_name') }}</dt><dd class="col-sm-9">{{ $student->father_name }}</dd>
                        <dt class="col-sm-3">{{ __('students.grandfather_name') }}</dt><dd class="col-sm-9">{{ $student->grandfather_name ?: '—' }}</dd>
                        <dt class="col-sm-3">{{ __('students.gender') }}</dt><dd class="col-sm-9">{{ $student->gender->studentLabel() }}</dd>
                        <dt class="col-sm-3">{{ __('students.birth_date') }}</dt><dd class="col-sm-9">{{ $student->birth_date ? \App\Support\JalaliDate::format($student->birth_date, 'long') : '—' }}</dd>
                        <dt class="col-sm-3">{{ __('students.admission_date') }}</dt><dd class="col-sm-9">{{ $student->admission_date ? \App\Support\JalaliDate::format($student->admission_date, 'long') : '—' }}</dd>
                        @if ($sensitive)
                            <dt class="col-sm-3">{{ __('students.tazkira_no') }}</dt><dd class="col-sm-9"><span dir="ltr">{{ $student->tazkira_no ?: '—' }}</span></dd>
                            <dt class="col-sm-3">{{ __('students.phone') }}</dt><dd class="col-sm-9"><span dir="ltr">{{ $student->phone ?: '—' }}</span></dd>
                            <dt class="col-sm-3">{{ __('students.province') }}</dt><dd class="col-sm-9">{{ collect([$student->province, $student->district, $student->village])->filter()->join('، ') ?: '—' }}</dd>
                            <dt class="col-sm-3">{{ __('students.address') }}</dt><dd class="col-sm-9">{{ $student->address ?: '—' }}</dd>
                            <dt class="col-sm-3">{{ __('students.notes') }}</dt><dd class="col-sm-9 text-pre-line">{{ $student->notes ?: '—' }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        @if ($sensitive)
            <div class="tab-pane fade" id="guardians-pane" role="tabpanel">
                @include('students.partials.guardians')
            </div>
            <div class="tab-pane fade" id="documents-pane" role="tabpanel">
                @include('students.partials.documents')
            </div>
        @endif

        <div class="tab-pane fade" id="history-pane" role="tabpanel">
            @can('update', $student)
                @if ($currentYear)
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @if ($currentEnrollment)
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#moveModal"><i class="fa-solid fa-right-left" aria-hidden="true"></i> {{ __('academics.move') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#leaveModal"><i class="fa-solid fa-door-open" aria-hidden="true"></i> {{ __('academics.leave') }}</button>
                        @elseif (in_array($student->status, [\App\Enums\StudentStatus::Active, \App\Enums\StudentStatus::Transferred, \App\Enums\StudentStatus::Dropped], true)
                                 && (! $yearEnrollment || in_array($yearEnrollment->status, [\App\Enums\EnrollmentStatus::Transferred, \App\Enums\EnrollmentStatus::Dropped], true)))
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#enrollModal"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('academics.enroll') }}</button>
                            @if ($yearEnrollment)<span class="form-text align-self-center">{{ __('academics.reenroll_hint') }}</span>@endif
                        @endif
                    </div>
                @endif
            @endcan
            @can('update', $student)
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <a href="{{ route('student-transfers.record', $student) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('students.record_print') }}</a>
                    <a href="{{ route('student-transfers.package', $student) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-export" aria-hidden="true"></i> {{ __('students.record_file') }}</a>
                    @if (in_array($student->status, [\App\Enums\StudentStatus::Active, \App\Enums\StudentStatus::Inactive], true) && ! $student->trashed())
                        <a href="{{ route('student-transfers.out.create', $student) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> {{ __('students.transfer_out') }}</a>
                    @endif
                    @unless ($student->trashed())
                        <a href="{{ route('student-transfers.in.create', $student) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> {{ __('students.transfer_in') }}</a>
                    @endunless
                    @if ($currentEnrollment)
                        <a href="{{ route('student-cards.index', ['student' => $student->id, 'print' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-id-badge" aria-hidden="true"></i> {{ __('students.id_card') }}</a>
                    @endif
                </div>
            @endcan
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('academics.year') }}</th>
                            <th>{{ __('academics.grade') }}</th>
                            <th>{{ __('academics.section') }}</th>
                            <th>{{ __('academics.roll_no') }}</th>
                            <th>{{ __('students.status') }}</th>
                            <th>{{ __('academics.date') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($student->enrollments as $enrollment)
                            <tr @class(['table-active' => $currentEnrollment && $enrollment->id === $currentEnrollment->id])>
                                <td class="fw-medium">@fa($enrollment->academicYear->name)</td>
                                <td>{{ $enrollment->section->gradeLevel->name }}</td>
                                <td>{{ $enrollment->section->name }} ({{ $enrollment->section->shift->label() }})</td>
                                <td>@fa($enrollment->roll_no)</td>
                                <td>
                                    <span class="badge {{ $enrollment->status->badgeClass() }}">{{ $enrollment->status->label() }}</span>
                                    @if ($enrollment->leave_reason)<div class="small text-secondary">{{ $enrollment->leave_reason }}</div>@endif
                                </td>
                                <td class="small">
                                    @jdate($enrollment->enrolled_on)
                                    @if ($enrollment->left_on) ← @jdate($enrollment->left_on)@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-3">{{ __('academics.no_history') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($student->status === \App\Enums\StudentStatus::Graduated && $student->certificate_no)
                <div class="card card-body mt-3 small">
                    <div><i class="fa-solid fa-award" aria-hidden="true"></i> {{ __('students.graduation') }}: @fa($student->certificate_no)@if ($student->certificate_date) — @jdate($student->certificate_date, 'long')@endif</div>
                    @if ($student->certificate_note)<div class="text-secondary">{{ $student->certificate_note }}</div>@endif
                </div>
            @endif

            @if ($sensitive && $transfers->isNotEmpty())
                <h2 class="h6 fw-bold mt-3">{{ __('students.transfers_title') }}</h2>
                <div class="card">
                    <ul class="list-group list-group-flush">
                        @foreach ($transfers as $t)
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2 small">
                                <span class="badge {{ $t->direction->badgeClass() }}">{{ $t->direction->label() }}</span>
                                <span class="flex-grow-1">{{ $t->other_school }} — @jdate($t->transfer_date)@if ($t->isCancelled()) <span class="badge text-bg-light border">{{ __('transfers.cancelled') }}</span>@endif</span>
                                <a href="{{ route('student-transfers.show', $t) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @foreach ($transfers->filter(fn ($t) => ! $t->isCancelled() && $t->previous_records) as $t)
                    <div class="mt-3">@include('students.partials.previous-records', ['records' => $t->previous_records, 'student' => $student])</div>
                @endforeach
            @endif
        </div>

        {{-- حاضری سال جاری --}}
        <div class="tab-pane fade" id="attendance-pane" role="tabpanel">
            @include('students.partials.attendance')
        </div>

        @if ($canSeeMarks)
            <div class="tab-pane fade" id="marks-pane" role="tabpanel">
                @include('students.partials.marks')
            </div>
        @endif

        @if ($fees)
            <div class="tab-pane fade" id="fees-pane" role="tabpanel">
                @include('students.partials.fees')
            </div>
        @endif
    </div>

    {{-- پنجره‌ها --}}
    @can('update', $student)
        @php($activating = $student->status === \App\Enums\StudentStatus::Inactive)
        <x-modal-form id="statusModal" :title="$activating ? __('students.activate_title') : __('students.deactivate_title')"
                      :action="route('students.status', $student)" method="PATCH"
                      :submit="$activating ? __('students.activate') : __('students.deactivate')"
                      :submit-class="$activating ? 'btn-success' : 'btn-secondary'">
            <input type="hidden" name="status" value="{{ $activating ? 'active' : 'inactive' }}">
            <x-form.textarea name="reason" :label="__('students.reason')" rows="2" :required="! $activating" maxlength="500" :use-old="old('_modal') === 'statusModal'" />
            <x-jalali-date name="date" :label="__('students.status_date')" :value="now()" />
        </x-modal-form>
    @endcan
    @can('update', $student)
        @if ($currentYear)
            @php($sectionOptions = $yearSections->mapWithKeys(fn ($s) => [$s->id => $s->label])->all())
            @if ($currentEnrollment)
                <x-modal-form id="moveModal" :title="__('academics.move_title')" :action="route('students.enrollment.move', $student)" method="PATCH" :submit="__('academics.move')">
                    <x-form.select name="section_id" :label="__('academics.section')" :options="$sectionOptions" :value="$currentEnrollment->section_id" required />
                    <x-form.textarea name="reason" :label="__('academics.leave_reason')" rows="2" maxlength="500" :use-old="old('_modal') === 'moveModal'" />
                </x-modal-form>
                <x-modal-form id="leaveModal" :title="__('academics.leave_title')" :action="route('students.enrollment.leave', $student)" method="PATCH" :submit="__('app.save')" submit-class="btn-secondary">
                    <x-form.select name="status" :label="__('academics.leave_type')" :options="['transferred' => __('academics.leave_transfer'), 'dropped' => __('academics.leave_drop')]" required />
                    <x-form.textarea name="reason" :label="__('academics.leave_reason')" rows="2" required maxlength="500" :placeholder="__('academics.leave_reason_ph')" :use-old="old('_modal') === 'leaveModal'" />
                    <x-jalali-date name="date" :label="__('academics.date')" :value="now()" :use-old="old('_modal') === 'leaveModal'" />
                </x-modal-form>
            @else
                <x-modal-form id="enrollModal" :title="__('academics.enroll_title', ['year' => \App\Support\Digits::toPersian($currentYear->name)])" :action="route('students.enrollment.store', $student)" :submit="__('academics.enroll')">
                    <x-form.select name="section_id" :label="__('academics.section')" :options="$sectionOptions" :placeholder="__('academics.choose_section')" required />
                    <x-jalali-date name="date" :label="__('academics.date')" :value="now()" :use-old="old('_modal') === 'enrollModal'" />
                </x-modal-form>
            @endif
        @endif
    @endcan
    @can('delete', $student)
        <x-modal-form id="deleteModal" :title="__('students.delete_title')" :action="route('students.destroy', $student)" method="DELETE"
                      :submit="__('students.delete')" submit-class="btn-danger">
            <p class="small text-secondary">{{ __('students.delete_hint') }}</p>
            <x-form.textarea name="reason" :label="__('students.reason')" rows="2" required maxlength="500" :use-old="old('_modal') === 'deleteModal'" />
        </x-modal-form>
    @endcan
@endsection
