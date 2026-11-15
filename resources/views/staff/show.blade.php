@extends('layouts.app', ['title' => $member->full_name])

@section('content')
    <div class="card profile-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <x-avatar :src="$member->photo_path ? route('staff.photo', $member) : null" size="84" :alt="$member->full_name" />
            <div class="flex-grow-1">
                <h1 class="h4 fw-bold mb-1">{{ $member->full_name }}</h1>
                <div class="text-secondary">{{ $member->staff_type->label() }}@if ($member->position) · {{ $member->position }}@endif</div>
                <div class="mt-1">
                    @if ($member->trashed())
                        <span class="badge text-bg-danger">{{ __('students.deleted_badge') }}</span>
                    @else
                        <span class="badge {{ $member->employment_status->badgeClass() }}">{{ $member->employment_status->label() }}</span>
                        @if ($member->status_reason)
                            <span class="small text-secondary">{{ $member->status_reason }} (@jdate($member->status_changed_on))</span>
                        @endif
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($member->trashed())
                    <form method="POST" action="{{ route('staff.restore', $member) }}" data-confirm="{{ __('staff.confirm_restore') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> {{ __('staff.restore') }}</button>
                    </form>
                @else
                    <a href="{{ route('staff.edit', $member) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal">{{ __('staff.change_status') }}</button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="fa-solid fa-trash" aria-hidden="true"></i> {{ __('staff.delete') }}</button>
                @endif
                <a href="{{ route('staff.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <dl class="row mb-0 info-list">
                        <dt class="col-sm-4">{{ __('staff.father_name') }}</dt><dd class="col-sm-8">{{ $member->father_name ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('staff.gender') }}</dt><dd class="col-sm-8">{{ $member->gender->staffLabel() }}</dd>
                        <dt class="col-sm-4">{{ __('staff.phone') }}</dt><dd class="col-sm-8"><span dir="ltr">{{ $member->phone ?: '—' }}</span></dd>
                        <dt class="col-sm-4">{{ __('staff.education') }}</dt><dd class="col-sm-8">{{ $member->education?->label() ?? '—' }}</dd>
                        <dt class="col-sm-4">{{ __('staff.specialization') }}</dt><dd class="col-sm-8">{{ $member->specialization ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('staff.hire_date') }}</dt><dd class="col-sm-8">{{ $member->hire_date ? \App\Support\JalaliDate::format($member->hire_date, 'long') : '—' }}</dd>
                        <dt class="col-sm-4">{{ __('staff.address') }}</dt><dd class="col-sm-8">{{ $member->address ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('staff.notes') }}</dt><dd class="col-sm-8 text-pre-line">{{ $member->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header fw-bold"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> {{ __('staff.user_account') }}</div>
                <div class="card-body">
                    @if ($member->user)
                        <code dir="ltr">{{ $member->user->username }}</code>
                        — {{ $member->user->roles->map->label()->join('، ') }}
                        @unless ($member->user->is_active)<span class="badge text-bg-secondary">{{ __('users.inactive') }}</span>@endunless
                        @can('users.manage')
                            <a href="{{ route('settings.users.edit', $member->user) }}" class="btn btn-sm btn-link">{{ __('app.edit') }}</a>
                        @endcan
                    @else
                        <span class="text-secondary small">{{ __('staff.no_user_account') }}</span>
                    @endif
                </div>
            </div>
            @if ($member->staff_type === \App\Enums\StaffType::Teacher)
                <div class="card card-body text-secondary small">{{ __('staff.subjects_soon') }}</div>
            @endif
        </div>
    </div>

    {{-- سوابق کاری --}}
    <div class="card mt-3" id="history">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">{{ __('staff.history') }}</span>
            @unless ($member->trashed())
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#historyAdd"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('staff.history_add') }}</button>
            @endunless
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('staff.history_title') }}</th>
                    <th>{{ __('staff.history_organization') }}</th>
                    <th>{{ __('staff.history_from') }}</th>
                    <th>{{ __('staff.history_to') }}</th>
                    <th>{{ __('staff.history_notes') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($member->workHistory as $entry)
                    <tr>
                        <td>{{ $entry->title }}</td>
                        <td>{{ $entry->organization }}</td>
                        <td class="small">@jdate($entry->from_date)</td>
                        <td class="small">@jdate($entry->to_date)</td>
                        <td class="small">{{ $entry->notes }}</td>
                        <td class="text-end">
                            @unless ($member->trashed())
                                <form method="POST" action="{{ route('staff.history.destroy', [$member, $entry]) }}" data-confirm="{{ __('staff.confirm_history_remove') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('app.delete') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">{{ __('staff.no_history') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @unless ($member->trashed())
        @php($own = old('_modal'))
        <x-modal-form id="historyAdd" :title="__('staff.history_add')" :action="route('staff.history.store', $member)" :submit="__('app.add')">
            <x-form.input name="title" :label="__('staff.history_title')" :placeholder="__('staff.ph.history_title')" required maxlength="150" :use-old="$own === 'historyAdd'" />
            <x-form.input name="organization" :label="__('staff.history_organization')" :placeholder="__('staff.ph.history_organization')" maxlength="150" :use-old="$own === 'historyAdd'" />
            <div class="row">
                <x-jalali-date class="col-6 mb-3" name="from_date" :label="__('staff.history_from')" />
                <x-jalali-date class="col-6 mb-3" name="to_date" :label="__('staff.history_to')" />
            </div>
            <x-form.input name="notes" :label="__('staff.history_notes')" maxlength="500" :use-old="$own === 'historyAdd'" />
        </x-modal-form>

        <x-modal-form id="statusModal" :title="__('staff.change_status')" :action="route('staff.status', $member)" method="PATCH" :submit="__('app.save')">
            <x-form.select name="employment_status" :label="__('staff.employment_status')" :options="\App\Enums\EmploymentStatus::options()" :value="$member->employment_status" required />
            <p class="form-text mt-0">{{ __('staff.left_deactivates_user') }}</p>
            <x-form.textarea name="reason" :label="__('staff.reason')" :placeholder="__('staff.ph.reason')" rows="2" maxlength="500" :use-old="$own === 'statusModal'" />
            <x-jalali-date name="date" :label="__('staff.status_date')" :value="now()" />
        </x-modal-form>

        <x-modal-form id="deleteModal" :title="__('staff.delete_title')" :action="route('staff.destroy', $member)" method="DELETE" :submit="__('staff.delete')" submit-class="btn-danger">
            <p class="small text-secondary">{{ __('staff.delete_hint') }}</p>
            <x-form.textarea name="reason" :label="__('staff.reason')" rows="2" required maxlength="500" :use-old="$own === 'deleteModal'" />
        </x-modal-form>
    @endunless
@endsection
