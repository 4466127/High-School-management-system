@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('year_review.title')])

@php
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
    $steps = ['marks', 'finalize', 'reexam', 'promotion'];
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-3">{{ __('year_review.title') }}@if ($review) — {{ __('academics.year') }} @fa($review['year']->name)@endif</h1>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('year_review.title') }}@if ($review) <span class="text-secondary fs-6">— {{ __('academics.year') }} @fa($review['year']->name)</span>@endif</h1>
            @if ($review)
                <a href="{{ route('exams.year-review.index', ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
            @endif
        </div>
    @endif

    @if (! $review)
        <div class="card card-body text-center text-secondary py-5">{{ __('academics.no_year') }}</div>
    @else
        @unless ($print)
            <div class="alert alert-light border small">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('year_review.intro') }}
                <ol class="mb-0 mt-1 ps-3">@foreach ($steps as $s)<li>{{ __('year_review.step_help.'.$s) }}</li>@endforeach</ol>
            </div>
        @endunless

        {{-- خلاصه‌ی سال --}}
        <div class="row g-2 mb-3">
            @foreach ([
                'students' => 'fa-users', 'passed' => 'fa-circle-check', 'conditional' => 'fa-circle-exclamation',
                'failed' => 'fa-circle-xmark', 'incomplete' => 'fa-hourglass-half', 'missing_marks' => 'fa-pen-to-square',
            ] as $key => $icon)
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-body py-2 text-center">
                        <div class="small text-secondary"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ __('year_review.totals.'.$key) }}</div>
                        <div class="fs-5 fw-bold">{{ $n($review['totals'][$key]) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($review['open_exams']->isNotEmpty())
            <div class="alert alert-warning small">
                {{ __('year_review.open_exams', ['exams' => $review['open_exams']->pluck('name')->implode('، ')]) }}
                @unless ($print)<a href="{{ route('exams.manage.index') }}" class="alert-link">{{ __('year_review.go_exams') }}</a>@endunless
            </div>
        @endif
        @if ($review['exams']->isEmpty())
            <div class="alert alert-warning small">{{ __('year_review.no_exams') }}</div>
        @endif
        <div @class(['alert small', 'alert-success' => $review['next_year'], 'alert-warning' => ! $review['next_year']])>
            @if ($review['next_year'])
                {{ __('year_review.next_year_ready', ['year' => $n($review['next_year']->name)]) }}
            @else
                {{ __('year_review.next_year_missing') }}
                @unless ($print)<a href="{{ route('academics.years.index') }}" class="alert-link">{{ __('year_review.go_years') }}</a>@endunless
            @endif
        </div>

        <div class="small text-secondary mb-2">{{ __('year_review.progress', ['done' => $n($review['totals']['done']), 'all' => $n(count($review['sections']))]) }}</div>
        <div @class(['card' => ! $print])>
            <div class="table-responsive">
                <table @class(['table align-middle mb-0', 'table-bordered table-sm' => $print])>
                    <thead>
                    <tr>
                        <th>{{ __('academics.section') }}</th>
                        <th class="text-center">{{ __('year_review.students') }}</th>
                        <th>{{ __('year_review.col_marks') }}</th>
                        <th>{{ __('year_review.col_results') }}</th>
                        <th>{{ __('year_review.col_reexam') }}</th>
                        <th>{{ __('year_review.col_promotion') }}</th>
                        <th>{{ __('year_review.next_step') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($review['sections'] as $row)
                        @php($s = $row['section'])
                        <tr>
                            <td class="fw-medium">{{ $s->label }}</td>
                            <td class="text-center">{{ $n($row['students']) }}</td>
                            <td class="small">
                                @if ($row['missing_marks'] === 0 && $row['students'] > 0)
                                    <i class="fa-solid fa-circle-check text-success" aria-hidden="true"></i> {{ __('year_review.marks_complete') }}
                                @elseif ($row['students'] > 0)
                                    <i class="fa-solid fa-hourglass-half text-warning" aria-hidden="true"></i> {{ $n($row['marks_percent']) }}٪ — {{ __('year_review.missing', ['count' => $n($row['missing_marks'])]) }}
                                @endif
                            </td>
                            <td class="small">
                                @if ($row['finalized'])<i class="fa-solid fa-lock text-success" aria-hidden="true"></i> {{ __('year_review.finalized') }}@else<span class="text-secondary">{{ __('year_review.live') }}</span>@endif
                                <div>
                                    <span class="text-success">{{ __('enums.final_result.passed') }} {{ $n($row['passed']) }}</span>
                                    · <span class="text-warning-emphasis">{{ __('enums.final_result.conditional') }} {{ $n($row['conditional']) }}</span>
                                    · <span class="text-danger">{{ __('enums.final_result.failed') }} {{ $n($row['failed']) }}</span>
                                    @if ($row['deprived']) · {{ __('enums.final_result.deprived') }} {{ $n($row['deprived']) }}@endif
                                    @if ($row['incomplete']) · <span class="text-secondary">{{ __('exams.incomplete') }} {{ $n($row['incomplete']) }}</span>@endif
                                </div>
                            </td>
                            <td class="small">
                                @if (! $row['finalized'])<span class="text-secondary">—</span>
                                @elseif ($row['reexam_pending'] > 0)<i class="fa-solid fa-hourglass-half text-warning" aria-hidden="true"></i> {{ __('year_review.reexam_pending', ['count' => $n($row['reexam_pending'])]) }}
                                @else<i class="fa-solid fa-circle-check text-success" aria-hidden="true"></i> {{ __('year_review.reexam_done') }}@endif
                            </td>
                            <td class="small">{{ __('year_review.decided', ['done' => $n($row['decided']), 'all' => $n($row['students'])]) }}</td>
                            <td class="small text-nowrap">
                                @php($step = $row['step'])
                                @if ($step === 'done')
                                    <span class="badge text-bg-success">{{ __('year_review.step.done') }}</span>
                                @elseif ($step === 'empty')
                                    <span class="text-secondary">{{ __('year_review.step.empty') }}</span>
                                @elseif ($print)
                                    {{ __('year_review.step.'.$step) }}
                                @else
                                    @php($link = match ($step) {
                                        'marks' => route('exams.marks.index', ['section' => $s->id]),
                                        'finalize' => route('exams.results.index', ['section' => $s->id]),
                                        'reexam' => route('exams.reexam.index', ['section' => $s->id]),
                                        default => route('academics.promotion.create', ['section' => $s->id]),
                                    })
                                    <a href="{{ $link }}" class="btn btn-sm btn-outline-primary">{{ __('year_review.step.'.$step) }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-4">{{ __('academics.no_sections') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
