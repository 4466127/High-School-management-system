{{-- زبانه‌ی نمرات پرونده: نمره‌های سال جاری و نتیجه (همان داده‌ی کارنامه) --}}
@php($fmt = fn ($n) => $n === null ? '—' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n)))
@if (! $reportCard)
    <div class="card card-body text-center text-secondary py-4">{{ __('exams.student_no_results') }}</div>
@else
    @php($result = $reportCard['result'] ? \App\Enums\FinalResult::from($reportCard['result']) : null)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        @if ($result)
            <span class="badge fs-6 {{ $result->badgeClass() }}">{{ $result->label() }}</span>
        @else
            <span class="badge fs-6 text-bg-light border text-secondary">{{ __('exams.incomplete') }}</span>
        @endif
        @if ($reportCard['provisional'])<span class="small text-warning-emphasis">{{ __('exams.provisional') }}</span>@endif
        <span class="small">{{ __('exams.total') }}: <b>{{ $fmt($reportCard['total']) }}</b> · {{ __('exams.average') }}: <b>{{ $fmt($reportCard['average']) }}</b>
            @if ($reportCard['rank']) · {{ __('exams.rank') }}: <b>@fa($reportCard['rank'])</b>@endif</span>
        <a href="{{ route('exams.reportcards.show', $reportCard['enrollment']) }}" class="btn btn-sm btn-outline-primary ms-auto"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('exams.reportcard') }}</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle text-center mb-0">
                <thead>
                <tr>
                    <th class="text-start">{{ __('academics.subject') }}</th>
                    @foreach ($reportCard['exams'] as $exam)
                        <th>{{ $exam['name'] }} <span class="small fw-normal text-secondary">({{ $fmt($exam['max_mark']) }})</span></th>
                    @endforeach
                    <th>{{ __('exams.final_mark') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($reportCard['subjects'] as $subject)
                    <tr>
                        <td class="text-start fw-medium">{{ $subject['name'] }}</td>
                        @foreach ($reportCard['exams'] as $exam)
                            @php($m = $subject['marks'][$exam['id']] ?? null)
                            <td>{{ $m ? ($m['absent'] ? __('exams.absent') : $fmt($m['mark'])) : '' }}</td>
                        @endforeach
                        <td @class(['fw-bold', 'text-danger' => $subject['passed'] === false])>{{ $subject['final'] !== null ? $fmt($subject['final']) : '…' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
