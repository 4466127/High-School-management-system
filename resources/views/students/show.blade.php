@extends('layouts.app', ['title' => $student->full_name])

@section('content')
    {{-- سربرگ پرونده --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <x-avatar :src="$student->photo_path ? route('students.photo', $student) : null" size="84" :alt="$student->full_name" />
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ $student->full_name }}</h1>
                <div class="text-secondary">
                    {{ __('students.father_name') }}: {{ $student->father_name }}
                    · {{ __('students.admission_no') }}: <span class="num">@fa($student->admission_no)</span>
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
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="info-pane" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0 info-list">
                        <dt class="col-sm-3">{{ __('students.admission_no') }}</dt><dd class="col-sm-9 num">@fa($student->admission_no)</dd>
                        <dt class="col-sm-3">{{ __('students.first_name') }}</dt><dd class="col-sm-9">{{ $student->first_name }}</dd>
                        <dt class="col-sm-3">{{ __('students.last_name') }}</dt><dd class="col-sm-9">{{ $student->last_name ?: '—' }}</dd>
                        <dt class="col-sm-3">{{ __('students.father_name') }}</dt><dd class="col-sm-9">{{ $student->father_name }}</dd>
                        <dt class="col-sm-3">{{ __('students.grandfather_name') }}</dt><dd class="col-sm-9">{{ $student->grandfather_name ?: '—' }}</dd>
                        <dt class="col-sm-3">{{ __('students.gender') }}</dt><dd class="col-sm-9">{{ $student->gender->label() }}</dd>
                        <dt class="col-sm-3">{{ __('students.birth_date') }}</dt><dd class="col-sm-9">{{ $student->birth_date ? \App\Support\JalaliDate::format($student->birth_date, 'long') : '—' }}</dd>
                        <dt class="col-sm-3">{{ __('students.admission_date') }}</dt><dd class="col-sm-9">{{ $student->admission_date ? \App\Support\JalaliDate::format($student->admission_date, 'long') : '—' }}</dd>
                        @if ($sensitive)
                            <dt class="col-sm-3">{{ __('students.tazkira_no') }}</dt><dd class="col-sm-9" dir="ltr">{{ $student->tazkira_no ?: '—' }}</dd>
                            <dt class="col-sm-3">{{ __('students.phone') }}</dt><dd class="col-sm-9" dir="ltr">{{ $student->phone ?: '—' }}</dd>
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
            <div class="card card-body text-secondary">{{ __('students.history_soon') }}</div>
        </div>
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
    @can('delete', $student)
        <x-modal-form id="deleteModal" :title="__('students.delete_title')" :action="route('students.destroy', $student)" method="DELETE"
                      :submit="__('students.delete')" submit-class="btn-danger">
            <p class="small text-secondary">{{ __('students.delete_hint') }}</p>
            <x-form.textarea name="reason" :label="__('students.reason')" rows="2" required maxlength="500" :use-old="old('_modal') === 'deleteModal'" />
        </x-modal-form>
    @endcan
@endsection
