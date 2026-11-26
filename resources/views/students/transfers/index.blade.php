@extends('layouts.app', ['title' => __('transfers.title')])

@php($query = array_filter($filters))

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('transfers.title') }}</h1>
        <a href="{{ route('student-transfers.export', $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('transfers.hint') }}</div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label for="t-q" class="form-label">{{ __('app.search') }}</label><input type="search" id="t-q" name="q" value="{{ $filters['q'] }}" class="form-control"></div>
            <div class="col-md-3">
                <label for="t-dir" class="form-label">{{ __('transfers.direction') }}</label>
                <select name="direction" id="t-dir" class="form-select"><option value="">{{ __('app.all') }}</option>@foreach (\App\Enums\TransferDirection::options() as $v => $l)<option value="{{ $v }}" @selected($filters['direction'] === $v)>{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-6 col-md-2"><label class="form-label" for="t-from">{{ __('app.from_date') }}</label><input type="text" id="t-from" name="from" value="{{ $filters['from'] }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            <div class="col-6 col-md-2"><label class="form-label" for="t-to">{{ __('app.to_date') }}</label><input type="text" id="t-to" name="to" value="{{ $filters['to'] }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            <div class="col-md-1"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>{{ __('transfers.transfer_date') }}</th><th>{{ __('students.full_name') }}</th><th>{{ __('transfers.direction') }}</th>
                    <th>{{ __('transfers.other_school') }}</th><th>{{ __('transfers.reason') }}</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($transfers as $t)
                    <tr @class(['text-secondary' => $t->isCancelled()])>
                        <td class="small text-nowrap">@jdate($t->transfer_date)</td>
                        <td><a href="{{ route('students.show', $t->student) }}">{{ $t->student->full_name }}</a> <span class="small text-secondary">@fa($t->student->admission_no)</span></td>
                        <td><span class="badge {{ $t->direction->badgeClass() }}">{{ $t->direction->label() }}</span>@if ($t->isCancelled()) <span class="badge text-bg-light border">{{ __('transfers.cancelled') }}</span>@endif</td>
                        <td>{{ $t->other_school }}@if ($t->other_province)<div class="small text-secondary">{{ $t->other_province }}</div>@endif</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($t->reason, 80) }}</td>
                        <td class="text-end"><a href="{{ route('student-transfers.show', $t) }}" class="btn btn-sm btn-outline-primary">{{ __('app.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('transfers.none') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $transfers->links() }}</div>
@endsection
