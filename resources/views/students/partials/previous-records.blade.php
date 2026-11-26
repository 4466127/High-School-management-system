{{-- سوابق مکتب قبلی از فایل سوابق (داده‌ی بیرونی، فقط نمایش با escape). $records، $student --}}
@php
    $fileName = trim(($records['student']['first_name'] ?? '').' '.($records['student']['last_name'] ?? ''));
    $mismatch = $fileName !== '' && \App\Support\PersianText::normalize($fileName) !== \App\Support\PersianText::normalize($student->full_name);
@endphp
<div class="card mb-3">
    <div class="card-header fw-medium">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> {{ __('transfers.previous_records') }}
        <span class="small text-secondary fw-normal">{{ __('transfers.previous_records_from', ['school' => $records['school']['name'] ?? '—', 'date' => $records['exported_at'] ? \App\Support\JalaliDate::format(\Carbon\CarbonImmutable::parse($records['exported_at'])) : '—']) }}</span>
    </div>
    @if ($mismatch)
        <div class="alert alert-warning m-2 py-2 small">{{ __('transfers.name_mismatch', ['name' => $fileName]) }}</div>
    @endif
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('transfers.year') }}</th><th>{{ __('transfers.grade') }}</th><th>{{ __('transfers.result') }}</th><th>{{ __('transfers.average') }}</th><th>{{ __('transfers.subjects') }}</th></tr></thead>
            <tbody>
            @foreach ($records['history'] ?? [] as $h)
                <tr>
                    <td>@fa($h['year'])</td>
                    <td>{{ $h['grade'] }} {{ $h['section'] }}@if (! empty($h['school'])) <span class="small text-secondary">({{ $h['school'] }})</span>@endif</td>
                    <td>{{ \App\Services\StudentRecordService::resultLabel($h['result']) }}</td>
                    <td>{{ $h['average'] !== null ? \App\Support\Digits::toPersian(\App\Services\MarkService::format($h['average'])) : '—' }}</td>
                    <td class="small">{{ collect($h['subjects'])->map(fn ($s) => $s['name'].': '.($s['final'] !== null ? \App\Support\Digits::toPersian(\App\Services\MarkService::format($s['final'])) : '—'))->join('، ') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
