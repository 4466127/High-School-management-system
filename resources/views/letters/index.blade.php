@php($out = $direction === \App\Enums\LetterDirection::Outgoing)
@extends('layouts.app', ['title' => __($out ? 'letters.outgoing_title' : 'letters.incoming_title')])

@php($query = array_filter($filters))

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __($out ? 'letters.outgoing_title' : 'letters.incoming_title') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route($out ? 'letters.outgoing.create' : 'letters.incoming.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __($out ? 'letters.create_outgoing' : 'letters.create_incoming') }}</a>
            <a href="{{ route('letters.export', $query + ['direction' => $direction->value]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
        </div>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($out ? 'letters.outgoing_hint' : 'letters.incoming_hint') }}</div>

    @if (! $out && $pendingCount > 0)
        <div class="alert alert-warning py-2 d-flex align-items-center gap-2">
            <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
            <span class="flex-grow-1">{{ __('letters.pending_count', ['count' => \App\Support\Digits::toPersian($pendingCount)]) }}</span>
            <a href="{{ route('letters.incoming.index', ['pending' => 1]) }}" class="btn btn-sm btn-warning">{{ __('letters.pending_only') }}</a>
        </div>
    @endif

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4 col-lg-3"><label for="f-q" class="form-label">{{ __('app.search') }}</label><input type="search" id="f-q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('letters.number') }}، {{ __('letters.subject') }}، {{ __('letters.organization') }}"></div>
            <div class="col-sm-6 col-md-4 col-lg-2">
                <label for="f-type" class="form-label">{{ __('letters.type') }}</label>
                <select name="type" id="f-type" class="form-select"><option value="">{{ __('app.all') }}</option>@foreach (\App\Enums\LetterType::options() as $v => $l)<option value="{{ $v }}" @selected($filters['type'] === $v)>{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-sm-6 col-md-4 col-lg-2">
                <label for="f-status" class="form-label">{{ __('letters.status') }}</label>
                <select name="status" id="f-status" class="form-select"><option value="">{{ __('app.all') }}</option>@foreach (\App\Enums\LetterStatus::options() as $v => $l)<option value="{{ $v }}" @selected($filters['status'] === $v)>{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-6 col-md-3 col-lg-2"><label class="form-label" for="f-from">{{ __('app.from_date') }}</label><input type="text" id="f-from" name="from" value="{{ $filters['from'] }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            <div class="col-6 col-md-3 col-lg-2"><label class="form-label" for="f-to">{{ __('app.to_date') }}</label><input type="text" id="f-to" name="to" value="{{ $filters['to'] }}" data-jdate dir="ltr" autocomplete="off" class="form-control" placeholder="{{ __('app.date_placeholder') }}"></div>
            @unless ($out)<input type="hidden" name="pending" value="{{ $filters['pending'] ? 1 : '' }}">@endunless
            <div class="col-md-2 col-lg-1"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('letters.number') }}</th>
                    <th>{{ __('letters.date') }}</th>
                    <th>{{ __('letters.subject') }}</th>
                    <th>{{ __($out ? 'letters.recipient' : 'letters.sender') }}</th>
                    <th>{{ __('letters.type') }}</th>
                    <th>{{ __('letters.status') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($letters as $letter)
                    <tr @class(['text-secondary' => $letter->isCancelled()])>
                        <td class="text-nowrap fw-medium" dir="ltr">{{ $letter->displayNumber() }}</td>
                        <td class="small text-nowrap">@jdate($letter->letter_date)</td>
                        <td><a href="{{ route($out ? 'letters.outgoing.show' : 'letters.incoming.show', $letter) }}" @class(['text-decoration-line-through' => $letter->isCancelled()])>{{ $letter->subject }}</a>
                            @if ($letter->student)<div class="small text-secondary">{{ $letter->student->full_name }}</div>@elseif ($letter->staff)<div class="small text-secondary">{{ $letter->staff->full_name }}</div>@endif
                        </td>
                        <td class="small">{{ collect([$letter->recipient, $letter->organization])->filter()->join(' — ') }}</td>
                        <td class="small">{{ $letter->type->label() }}</td>
                        <td>
                            <span class="badge {{ $letter->status->badgeClass() }}">{{ $out ? $letter->status->label() : ($letter->isCancelled() ? $letter->status->label() : \App\Enums\LetterDirection::Incoming->label()) }}</span>
                            @if (! $out && $letter->awaitingResponse())<span class="badge text-bg-warning">{{ __('letters.awaiting_response') }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('letters.none') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $letters->links() }}</div>
@endsection
