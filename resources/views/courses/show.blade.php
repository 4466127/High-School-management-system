@extends('layouts.app', ['title' => $course->name])

@php
    $m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents));
    $groupOptions = $course->groups->mapWithKeys(fn ($g) => [$g->id => $g->name.' ('.$g->shift->label().')'])->all();
    $teacherOptions = $teachers->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' — '.$s->staff_type->label()])->all();
@endphp

@section('content')
    <div class="card profile-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="section-badge section-badge-lg"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ $course->name }} <span class="badge {{ $course->status->badgeClass() }} fs-6 align-middle">{{ $course->status->label() }}</span></h1>
                <div class="text-secondary small">
                    {{ $course->field }} · {{ $course->season->label() }}
                    · @jdate($course->start_date) ← @if ($course->end_date)@jdate($course->end_date)@else{{ __('courses.open_ended') }}@endif
                </div>
                @if ($course->description)<div class="small mt-1">{{ $course->description }}</div>@endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($canLedger)
                    <a href="{{ route('courses.ledger.show', $course) }}" class="btn btn-outline-success"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> {{ __('courses.open_ledger') }}</a>
                @endif
                @if ($canManage)
                    <a href="{{ route('courses.list.edit', $course) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('courses.edit') }}</a>
                @endif
                <a href="{{ route('courses.list.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#groups-pane" type="button" role="tab">{{ __('courses.tab_groups') }} <span class="badge text-bg-light border">@fa($course->groups->count())</span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#participants-pane" type="button" role="tab">{{ __('courses.tab_participants') }} <span class="badge text-bg-light border">@fa($course->groups->flatMap->participants->where('status', \App\Enums\ParticipantStatus::Active)->count())</span></button></li>
        @if ($summary)
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#finance-pane" type="button" role="tab">{{ __('courses.tab_finance') }}</button></li>
        @endif
    </ul>

    <div class="tab-content">
        {{-- گروه‌ها --}}
        <div class="tab-pane fade show active" id="groups-pane" role="tabpanel">
            @if ($canManage)
                <div class="mb-2 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('courses.group_create') }}</button>
                </div>
            @endif
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('courses.group_name') }}</th>
                            <th>{{ __('courses.shift') }}</th>
                            <th>{{ __('courses.teacher') }}</th>
                            <th>{{ __('courses.schedule') }}</th>
                            <th class="text-center">{{ __('courses.participants_count') }}</th>
                            <th class="text-end"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($course->groups as $group)
                            <tr @class(['text-secondary' => ! $group->is_active])>
                                <td class="fw-medium">{{ $group->name }}@unless ($group->is_active) <span class="badge text-bg-light border">{{ __('courses.inactive') }}</span>@endunless</td>
                                <td>{{ $group->shift->label() }}</td>
                                <td>{{ $group->teacher?->full_name ?? '—' }}</td>
                                <td class="small">{{ $group->schedule }}</td>
                                <td class="text-center">@fa($group->participants->where('status', \App\Enums\ParticipantStatus::Active)->count())</td>
                                <td class="text-end text-nowrap">
                                    @can('viewAttendance', [\App\Models\Course::class, $group])
                                        <a href="{{ route('courses.attendance.show', $group) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-clipboard-user" aria-hidden="true"></i> {{ __('courses.open_sheet') }}</a>
                                    @endcan
                                    @if ($canManage)
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#groupEdit{{ $group->id }}">{{ __('app.edit') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('courses.no_groups') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- اشتراک‌کنندگان --}}
        <div class="tab-pane fade" id="participants-pane" role="tabpanel">
            @if ($canManage && $course->groups->isNotEmpty())
                <div class="card form-section mb-3">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <span><i class="fa-solid fa-user-graduate" aria-hidden="true"></i> {{ __('courses.add_students') }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#externalCreate"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('courses.add_external') }}</button>
                    </div>
                    <div class="card-body">
                        <p class="small text-secondary">{{ __('courses.add_students_hint') }}</p>
                        <form method="GET" action="{{ route('courses.list.show', $course) }}#participants" class="row g-2 align-items-end mb-3">
                            <div class="col-sm-8 col-lg-5">
                                <label for="pick" class="form-label">{{ __('courses.pick_section') }}</label>
                                <select name="pick" id="pick" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($pickSection && $pickSection->id === $s->id)>{{ $s->label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-sm-4 col-lg-3"><button type="submit" class="btn btn-outline-primary w-100">{{ __('courses.pick') }}</button></div>
                        </form>

                        @if ($pickSection)
                            <form method="POST" action="{{ route('courses.list.participants.students', $course) }}">
                                @csrf
                                @error('student_ids')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                                <div class="border rounded p-2 mb-3 course-pick-list">
                                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-check-all="student_ids[]"><i class="fa-solid fa-check-double" aria-hidden="true"></i> {{ __('courses.select_all') }}</button>
                                    <div class="row g-1">
                                        @forelse ($sectionStudents as $row)
                                            <div class="col-sm-6 col-lg-4">
                                                <label @class(['form-check', 'text-secondary' => $row['in_course']])>
                                                    <input type="checkbox" class="form-check-input" name="student_ids[]" value="{{ $row['enrollment']->student_id }}" @disabled($row['in_course'])>
                                                    @fa($row['enrollment']->roll_no) — {{ $row['enrollment']->student->full_name }}
                                                    <span class="small text-secondary">({{ $row['enrollment']->student->father_name }})</span>
                                                    @if ($row['in_course'])<span class="badge text-bg-light border">{{ __('courses.already_in') }}</span>@endif
                                                </label>
                                            </div>
                                        @empty
                                            <div class="col-12 text-secondary small">{{ __('academics.no_students_in_section') }}</div>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="row g-2 align-items-end">
                                    <x-form.select class="col-sm-5 mb-0" name="course_group_id" :label="__('courses.group')" :options="$groupOptions" required />
                                    <x-jalali-date class="col-sm-4 mb-0" name="joined_on" :label="__('courses.joined_on')" :value="now()" required />
                                    <div class="col-sm-3"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-user-check" aria-hidden="true"></i> {{ __('courses.add_students') }}</button></div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @forelse ($course->groups as $group)
                <div class="card mb-3">
                    <div class="card-header fw-medium">{{ $group->name }} · {{ $group->shift->label() }} @if ($group->teacher)<span class="small text-secondary">· {{ $group->teacher->full_name }}</span>@endif</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('students.full_name') }}</th>
                                <th>{{ __('students.father_name') }}</th>
                                <th>{{ __('courses.phone') }}</th>
                                <th>{{ __('courses.joined_on') }}</th>
                                <th>{{ __('courses.participant_status') }}</th>
                                <th title="{{ __('courses.att_summary_hint') }}">{{ __('courses.att_summary') }}</th>
                                @if ($canManage)<th></th>@endif
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($group->participants->sortBy(fn ($p) => [$p->status->value !== 'active', $p->display_name]) as $p)
                                @php($att = $attendance->get($p->id))
                                <tr>
                                    <td class="fw-medium">
                                        @if ($p->student)
                                            @can('view', $p->student)<a href="{{ route('students.show', $p->student) }}" class="text-decoration-none">{{ $p->display_name }}</a>@else{{ $p->display_name }}@endcan
                                        @else
                                            {{ $p->display_name }} <span class="badge text-bg-light border">{{ __('courses.external') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $p->display_father_name }}</td>
                                    <td dir="ltr" class="text-end small">{{ $p->phone }}</td>
                                    <td class="small">@jdate($p->joined_on)</td>
                                    <td><span class="badge {{ $p->status->badgeClass() }}">{{ $p->status->label() }}</span>@if ($p->note)<div class="small text-secondary">{{ $p->note }}</div>@endif</td>
                                    <td class="small text-nowrap">@if ($att)@fa((int) $att->present + (int) $att->late) / <span class="text-danger">@fa((int) $att->absent)</span> <span class="text-secondary">(@fa((int) $att->total))</span>@else—@endif</td>
                                    @if ($canManage)
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#participant{{ $p->id }}">{{ __('app.edit') }}</button></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-secondary py-3">{{ __('courses.no_participants') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card card-body text-center text-secondary py-4">{{ __('courses.no_groups') }}</div>
            @endforelse
        </div>

        {{-- خلاصه‌ی مالی --}}
        @if ($summary)
            <div class="tab-pane fade" id="finance-pane" role="tabpanel">
                @include('courses.ledger._summary', ['summary' => $summary])
                <a href="{{ route('courses.ledger.show', $course) }}" class="btn btn-outline-success"><i class="fa-solid fa-book" aria-hidden="true"></i> {{ __('courses.open_ledger') }}</a>
            </div>
        @endif
    </div>

    @if ($canManage)
        <x-modal-form id="groupCreate" :title="__('courses.group_create')" :action="route('courses.list.groups.store', $course)" :submit="__('app.save')">
            @include('courses._group-fields', ['group' => null, 'useOld' => old('_modal') === 'groupCreate'])
        </x-modal-form>
        @foreach ($course->groups as $group)
            <x-modal-form id="groupEdit{{ $group->id }}" :title="__('courses.group_edit')" :action="route('courses.list.groups.update', $group)" method="PUT" :submit="__('app.save')">
                @include('courses._group-fields', ['group' => $group, 'useOld' => old('_modal') === 'groupEdit'.$group->id])
            </x-modal-form>
            @foreach ($group->participants as $p)
                <x-modal-form id="participant{{ $p->id }}" :title="__('courses.participant_edit').' — '.$p->display_name" :action="route('courses.list.participants.update', $p)" method="PUT" :submit="__('app.save')">
                    <x-form.select name="course_group_id" :label="__('courses.group')" :options="$groupOptions" :value="$p->course_group_id" required />
                    <x-form.select name="status" :label="__('courses.participant_status')" :options="\App\Enums\ParticipantStatus::options()" :value="$p->status" required />
                    <x-form.input class="mb-0" name="note" :label="__('courses.note')" :value="$p->note" maxlength="255" :use-old="old('_modal') === 'participant'.$p->id" />
                </x-modal-form>
            @endforeach
        @endforeach

        @if ($course->groups->isNotEmpty())
            <x-modal-form id="externalCreate" :title="__('courses.add_external')" :action="route('courses.list.participants.external', $course)" :submit="__('app.save')">
                @php($open = old('_modal') === 'externalCreate')
                <p class="small text-secondary">{{ __('courses.add_external_hint') }}</p>
                <div class="row">
                    <x-form.input class="col-md-6 mb-3" name="name" :label="__('students.full_name')" required maxlength="150" :use-old="$open" />
                    <x-form.input class="col-md-6 mb-3" name="father_name" :label="__('students.father_name')" maxlength="150" :use-old="$open" />
                    <x-form.input class="col-md-6 mb-3" name="phone" :label="__('courses.phone')" ltr maxlength="30" :use-old="$open" />
                    <x-form.select class="col-md-6 mb-3" name="gender" :label="__('courses.gender')" :options="\App\Enums\Gender::studentOptions()" placeholder="—" />
                    <x-form.select class="col-md-6 mb-3" name="course_group_id" :label="__('courses.group')" :options="$groupOptions" required />
                    <x-jalali-date class="col-md-6 mb-3" name="joined_on" :label="__('courses.joined_on')" :value="now()" required :use-old="$open" />
                    @can('ledger', \App\Models\Course::class)
                        <x-form.input class="col-md-6 mb-3" name="fee" :label="__('courses.fee')" inputmode="decimal" maxlength="20" :use-old="$open" :placeholder="__('courses.fee_ph')" :help="__('courses.fee_help')" />
                    @endcan
                    <x-form.input class="col-12 mb-0" name="note" :label="__('courses.note')" maxlength="255" :use-old="$open" />
                </div>
            </x-modal-form>
        @endif
    @endif
@endsection
