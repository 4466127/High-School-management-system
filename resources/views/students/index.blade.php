@extends('layouts.app', ['title' => __('students.title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('students.title') }}</h1>
        @can('create', \App\Models\Student::class)
            <a href="{{ route('students.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('students.create') }}
            </a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label">{{ __('app.search') }}</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('students.search_placeholder') }}" autofocus>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label">{{ __('students.status') }}</label>
                <select id="status" name="status" class="form-select">
                    <option value="all" @selected($status === 'all')>{{ __('students.all_statuses') }}</option>
                    @foreach (\App\Enums\StudentStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                    @if ($canManage)
                        <option value="deleted" @selected($status === 'deleted')>{{ __('students.deleted_records') }}</option>
                    @endif
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="gender" class="form-label">{{ __('students.gender') }}</label>
                <select id="gender" name="gender" class="form-select">
                    <option value="">{{ __('students.all_genders') }}</option>
                    @foreach (\App\Enums\Gender::studentOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(request('gender') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('app.search') }}</button>
                <a href="{{ route('students.index') }}" class="btn btn-light">{{ __('app.clear_filter') }}</a>
            </div>
        </div>
    </form>

    <div class="small text-secondary mb-2">{{ __('students.count', ['count' => \App\Support\Digits::toPersian($students->total())]) }}</div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 56px"></th>
                    <th>{{ __('students.admission_no') }}</th>
                    <th>{{ __('students.full_name') }}</th>
                    <th>{{ __('students.father_name') }}</th>
                    <th>{{ __('students.gender') }}</th>
                    @if ($canManage)
                        <th>{{ __('students.phone') }}</th>
                    @endif
                    <th>{{ __('students.status') }}</th>
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td><x-avatar :src="$student->photo_path ? route('students.photo', $student) : null" size="40" /></td>
                        <td>@fa($student->admission_no)</td>
                        <td><a href="{{ route('students.show', $student) }}" class="fw-medium text-decoration-none">{{ $student->full_name }}</a></td>
                        <td>{{ $student->father_name }}</td>
                        <td>{{ $student->gender->studentLabel() }}</td>
                        @if ($canManage)
                            <td dir="ltr" class="text-end small">{{ $student->phone }}</td>
                        @endif
                        <td>
                            @if ($student->trashed())
                                <span class="badge text-bg-danger">{{ __('students.deleted_badge') }}</span>
                            @else
                                <span class="badge {{ $student->status->badgeClass() }}">{{ $student->status->label() }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('students.show', $student) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">{{ __('app.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $students->links() }}</div>
@endsection
