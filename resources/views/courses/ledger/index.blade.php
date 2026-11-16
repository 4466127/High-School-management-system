@extends('layouts.app', ['title' => __('courses.ledger_title')])

@php($m = fn (int $cents) => \App\Support\Money::format(\App\Support\Money::fromCents($cents)))

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('courses.ledger_title') }}</h1>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('courses.ledger_intro') }}</div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('courses.name') }}</th>
                    <th>{{ __('courses.status') }}</th>
                    <th class="text-end">{{ __('courses.sum_donations') }}</th>
                    <th class="text-end">{{ __('courses.sum_donations_direct') }}</th>
                    <th class="text-end">{{ __('courses.sum_salaries') }}</th>
                    <th class="text-end">{{ __('courses.sum_expenses') }}</th>
                    <th class="text-end">{{ __('courses.sum_balance') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($courses as $course)
                    @php($s = $summaries->get($course->id))
                    <tr>
                        <td class="fw-medium"><a href="{{ route('courses.ledger.show', $course) }}" class="text-decoration-none">{{ $course->name }}</a></td>
                        <td><span class="badge {{ $course->status->badgeClass() }}">{{ $course->status->label() }}</span></td>
                        <td class="text-end">{{ $m($s['donations_total'] ?? 0) }}</td>
                        <td class="text-end small">{{ $m($s['donations_direct'] ?? 0) }}</td>
                        <td class="text-end">{{ $m($s['salaries'] ?? 0) }}</td>
                        <td class="text-end">{{ $m($s['expenses'] ?? 0) }}</td>
                        <td @class(['text-end fw-bold', 'text-danger' => ($s['balance'] ?? 0) < 0])>{{ $m($s['balance'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-5">{{ __('courses.none') }}</td></tr>
                @endforelse
                </tbody>
                @if ($courses->isNotEmpty())
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="2">{{ __('courses.all_courses_total') }}</td>
                        <td class="text-end">{{ $m($totals['donations_total']) }}</td>
                        <td class="text-end">{{ $m($totals['donations_direct']) }}</td>
                        <td class="text-end">{{ $m($totals['salaries']) }}</td>
                        <td class="text-end">{{ $m($totals['expenses']) }}</td>
                        <td class="text-end">{{ $m($totals['balance']) }}</td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
