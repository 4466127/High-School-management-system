{{--
    جدول هفته: سطرها روزها، ستون‌ها ساعت‌ها (تفریح ستون باریک).
    $mode: section | teacher | room — هر خانه بسته به نوع چیز دیگری نشان می‌دهد.
    $editable: خانه‌ها دکمه‌اند و پنجره‌ی #ttCell را باز می‌کنند (school.js ← data-tt-cell).
--}}
@php($editable ??= false)
<div class="table-responsive">
    <table class="table table-bordered align-middle text-center timetable-grid mb-0">
        <thead>
        @php($byShift = $periods->groupBy(fn ($p) => $p->shift->value))
        @if ($byShift->count() > 1)
            <tr class="tt-shift-row">
                <th class="tt-day"></th>
                @foreach ($byShift as $shiftPeriods)
                    <th colspan="{{ $shiftPeriods->count() }}">{{ $shiftPeriods->first()->shift->label() }}</th>
                @endforeach
            </tr>
        @endif
        <tr>
            <th class="tt-day">{{ __('timetable.day') }}</th>
            @foreach ($periods as $p)
                <th @class(['tt-break' => $p->is_break, 'tt-shift-start' => $byShift->count() > 1 && $loop->index > 0 && $periods[$loop->index - 1]->shift !== $p->shift])>
                    {{ $p->name }}
                    @if ($p->is_break)
                        <div class="tt-time">{{ \App\Support\Clock::short($p->start_time) }}<br>{{ \App\Support\Clock::short($p->end_time) }}</div>
                    @else
                        <div class="tt-time">{{ $p->timeRange() }}</div>
                    @endif
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($days as $day)
            <tr>
                <th class="tt-day">{{ __('school.weekdays.'.$day) }}</th>
                @foreach ($periods as $p)
                    @if ($p->is_break)
                        <td class="tt-break"></td>
                        @continue
                    @endif
                    @php($e = $grid[$day][$p->id] ?? null)
                    <td @class(['tt-cell', 'tt-empty' => ! $e])>
                        @if ($editable)
                            <button type="button" class="tt-btn" data-tt-cell
                                    data-day="{{ $day }}" data-period="{{ $p->id }}"
                                    data-label="{{ __('school.weekdays.'.$day) }} — {{ $p->name }}"
                                    data-subject="{{ $e?->subject_id }}" data-staff="{{ $e?->staff_id }}" data-room="{{ $e?->room_id }}" data-note="{{ $e?->note }}"
                                    aria-label="{{ __('school.weekdays.'.$day) }} — {{ $p->name }}">
                        @endif
                        @if ($e)
                            @if ($mode === 'section')
                                <div class="tt-subject">{{ $e->subject->name }}</div>
                                @if ($e->teacher)<div class="tt-meta">{{ $e->teacher->full_name }}</div>@endif
                            @else
                                <div class="tt-subject">{{ $e->section->gradeLevel->name }} {{ $e->section->name }}</div>
                                <div class="tt-meta">{{ $e->subject->name }}@if ($mode === 'room' && $e->teacher) — {{ $e->teacher->full_name }}@endif</div>
                            @endif
                            @if ($mode !== 'room' && $e->room)<div class="tt-meta"><i class="fa-solid fa-door-open" aria-hidden="true"></i> {{ $e->room->name }}</div>@endif
                            @if ($e->note)<div class="tt-meta fst-italic">{{ $e->note }}</div>@endif
                        @elseif ($editable)
                            <span class="tt-add"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                        @endif
                        @if ($editable)</button>@endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
