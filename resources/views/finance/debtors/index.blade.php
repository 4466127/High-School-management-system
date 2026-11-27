@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('finance.debtors_title')])

@php($m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents), false))

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('finance.debtors_title') }}@if ($year) — {{ __('academics.year') }} @fa($year->name)@endif @if ($section) — {{ $section->label }}@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('finance.debtors_title') }}</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.debtors.index', array_filter(['section' => $section?->id, 'print' => 1])) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('finance.debtors.export', array_filter(['section' => $section?->id])) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
            </div>
        </div>
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-8 col-lg-5">
                    <label for="d-section" class="form-label">{{ __('academics.section') }}</label>
                    <select name="section" id="d-section" class="form-select" data-submit-on-change>
                        <option value="">{{ __('app.all') }}</option>
                        @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-8 col-lg-3">
                    <label for="d-q" class="form-label">{{ __('app.search') }}</label>
                    <input type="search" name="q" id="d-q" value="{{ $q }}" class="form-control" placeholder="{{ __('finance.find_student') }}">
                </div>
                <div class="col-sm-4 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.view') }}</button></div>
            </div>
        </form>
    @endif

    <div class="fw-bold mb-2">{{ __('finance.debtors_total', ['amount' => \App\Support\Money::format(\App\Support\Money::fromCents($sum))]) }}</div>
    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('students.full_name') }}</th>
                    <th>{{ __('students.father_name') }}</th>
                    <th>{{ __('academics.section') }}</th>
                    <th>{{ __('students.phone') }}</th>
                    <th class="text-end">{{ __('finance.net') }}</th>
                    <th class="text-end">{{ __('finance.paid') }}</th>
                    <th class="text-end">{{ __('finance.remaining') }}</th>
                    @unless ($print)<th></th>@endunless
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    @php($student = $r->enrollment->student)
                    <tr>
                        <td>@fa(($print ? 0 : $rows->firstItem() - 1) + $loop->iteration)</td>
                        <td class="fw-medium">{{ $student->full_name }}</td>
                        <td>{{ $student->father_name }}</td>
                        <td class="small">{{ $r->enrollment->section->shortLabel }}</td>
                        <td class="small" dir="ltr">{{ $student->phone }}</td>
                        <td class="text-end">{{ $m($r->total - $r->discount) }}</td>
                        <td class="text-end">{{ $m($r->paid) }}</td>
                        <td class="text-end fw-bold text-danger">{{ $m($r->remaining) }}</td>
                        @unless ($print)
                            <td class="text-end text-nowrap">
                                @can('finance.manage')
                                    <a href="{{ route('finance.payments.create', $student) }}" class="btn btn-sm btn-success">{{ __('finance.receive') }}</a>
                                @endcan
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-4">{{ __('finance.no_debtors') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @unless ($print)<div class="mt-3">{{ $rows->links() }}</div>@endunless
    @if ($print)
        <div class="signatures"><div>{{ __('app.signature_principal') }}</div></div>
    @endif
@endsection
