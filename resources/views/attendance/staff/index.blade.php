@extends('layouts.app', ['title' => __('attendance.staff_title')])

@php
    $statuses = \App\Enums\AttendanceStatus::cases();
    $dayKey = $date->format('Y-m-d');
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('attendance.staff_title') }} <span class="text-secondary fs-6">— @jdate($date, 'full')</span></h1>
        <a href="{{ route('attendance.staff.monthly') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> {{ __('attendance.staff_monthly_link') }}</a>
    </div>

    @include('attendance.partials.date-bar', ['route' => 'attendance.staff.index'])

    @if ($isHoliday)
        <div class="alert alert-secondary"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i> {{ __('attendance.holiday_notice') }}</div>
    @endif
    @if (! $canRecord)
        <div class="alert alert-info">{{ __('attendance.future_notice') }}</div>
    @elseif ($records->isNotEmpty())
        <div class="alert alert-warning small">
            <i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('attendance.sheet_correct_hint') }}
            @if ($recorder) — {{ __('attendance.recorded_by', ['name' => $recorder->name]) }}@endif
        </div>
    @else
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('attendance.sheet_hint') }} {{ __('attendance.staff_hint') }}</div>
    @endif

    @error('status')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @if ($staff->isEmpty())
        <div class="card card-body text-center text-secondary py-5">{{ __('attendance.staff_empty') }}</div>
    @elseif ($canRecord)
        <form method="POST" action="{{ route('attendance.staff.store') }}" data-att-form>
            @csrf
            <input type="hidden" name="date" value="{{ $dayKey }}">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <button type="button" class="btn btn-sm btn-outline-success" data-att-all="present"><i class="fa-solid fa-check-double" aria-hidden="true"></i> {{ __('attendance.all_present') }}</button>
            </div>

            <div class="card att-sheet">
                <ul class="list-group list-group-flush">
                    @foreach ($staff as $id => $member)
                        @php
                            $record = $records->get($id);
                            $default = $member->employment_status === \App\Enums\EmploymentStatus::OnLeave ? 'excused' : 'present';
                            $current = old('status.'.$id, $record?->status->value ?? $default);
                        @endphp
                        <li class="list-group-item att-row">
                            <div class="att-who">
                                <x-avatar :src="$member->photo_path ? route('staff.photo', $member) : null" size="36" />
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate">{{ $member->full_name }}</div>
                                    <div class="small text-secondary text-truncate">
                                        {{ $member->staff_type->label() }}@if ($member->position) · {{ $member->position }}@endif
                                        @if ($member->employment_status === \App\Enums\EmploymentStatus::OnLeave)
                                            <span class="badge {{ $member->employment_status->badgeClass() }}">{{ $member->employment_status->label() }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="att-choices" role="radiogroup" aria-label="{{ $member->full_name }}">
                                @foreach ($statuses as $status)
                                    <input type="radio" class="btn-check" name="status[{{ $id }}]" id="ss-{{ $id }}-{{ $status->value }}" value="{{ $status->value }}" autocomplete="off" @checked($current === $status->value)>
                                    <label class="btn btn-outline-{{ $status->buttonClass() }}" for="ss-{{ $id }}-{{ $status->value }}" title="{{ $status->label() }}">
                                        <span class="d-none d-md-inline">{{ $status->label() }}</span><span class="d-md-none">{{ $status->code() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <input type="text" name="note[{{ $id }}]" value="{{ old('note.'.$id, $record?->note) }}" maxlength="255"
                                   class="form-control form-control-sm att-note" placeholder="{{ __('attendance.note_ph') }}" aria-label="{{ __('attendance.note') }} — {{ $member->full_name }}">
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="att-savebar">
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($statuses as $status)
                        <span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}: <span data-att-count="{{ $status->value }}">—</span></span>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('attendance.save_sheet') }}</button>
            </div>
        </form>
    @endif
@endsection
