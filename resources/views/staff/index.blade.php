@extends('layouts.app', ['title' => __('staff.title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('staff.title') }}</h1>
        <a href="{{ route('staff.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('staff.create') }}
        </a>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label">{{ __('app.search') }}</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('staff.search_placeholder') }}" autofocus>
            </div>
            <div class="col-6 col-md-2">
                <label for="type" class="form-label">{{ __('staff.staff_type') }}</label>
                <select id="type" name="type" class="form-select">
                    <option value="">{{ __('staff.all_types') }}</option>
                    @foreach (\App\Enums\StaffType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label">{{ __('staff.employment_status') }}</label>
                <select id="status" name="status" class="form-select">
                    <option value="all" @selected($status === 'all')>{{ __('staff.all_statuses') }}</option>
                    @foreach (\App\Enums\EmploymentStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                    <option value="deleted" @selected($status === 'deleted')>{{ __('staff.deleted_records') }}</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('app.search') }}</button>
                <a href="{{ route('staff.index') }}" class="btn btn-light">{{ __('app.clear_filter') }}</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 56px"></th>
                    <th>{{ __('students.full_name') }}</th>
                    <th>{{ __('staff.staff_type') }}</th>
                    <th>{{ __('staff.position') }}</th>
                    <th>{{ __('staff.phone') }}</th>
                    <th>{{ __('staff.employment_status') }}</th>
                    <th>{{ __('staff.user_account') }}</th>
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td><x-avatar :src="$member->photo_path ? route('staff.photo', $member) : null" size="40" /></td>
                        <td>
                            <a href="{{ route('staff.show', $member) }}" class="fw-medium text-decoration-none">{{ $member->full_name }}</a>
                            @if ($member->father_name)<div class="small text-secondary">{{ __('staff.father_name') }}: {{ $member->father_name }}</div>@endif
                        </td>
                        <td>{{ $member->staff_type->label() }}</td>
                        <td class="small">{{ $member->position }}</td>
                        <td dir="ltr" class="text-end small">{{ $member->phone }}</td>
                        <td>
                            @if ($member->trashed())
                                <span class="badge text-bg-danger">{{ __('students.deleted_badge') }}</span>
                            @else
                                <span class="badge {{ $member->employment_status->badgeClass() }}">{{ $member->employment_status->label() }}</span>
                            @endif
                        </td>
                        <td dir="ltr" class="text-end small">@if ($member->user)<code>{{ $member->user->username }}</code>@endif</td>
                        <td class="text-end"><a href="{{ route('staff.show', $member) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">{{ __('app.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $staff->links() }}</div>
@endsection
