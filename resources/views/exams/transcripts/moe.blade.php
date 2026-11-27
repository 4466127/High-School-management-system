{{--
    فورم رسمی وزارت معارف: «فورم اخذ ترانسکریپ (نمرات سه ساله) متعلم» (نمونه‌ی PDF وزارت، resources/documents).
    هر متعلم دو صفحه‌ی A4: ۱) شهرت متعلم، معلومات مکتب و نمرات سه ساله به عدد و به حروف؛ ۲) تصدیق مکتب و خانه‌های ریاست معارف (خالی، با دست).
    $items: [student, data (TranscriptService::build), official (TranscriptService::officialRows)]
--}}
@extends('layouts.print', ['title' => __('moe_transcript.title'), 'noHeader' => true])

@php
    $m = 'moe_transcript.';
    $fig = fn ($n) => $n === null ? '' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n));
    $words = fn ($n) => $n === null ? '' : \App\Support\NumberWords::fa($n);
    $set = fn (string $k) => trim((string) \App\Models\Setting::get($k));
    $stateLines = array_values(array_filter(preg_split('/\R/u', $set('header_line')) ?: [])) ?: __($m.'state_lines');
    $province = $set('province');
    $schoolName = $set('school_name');
    $sample ??= false;
@endphp

@section('content')
    @if ($sample)<div class="alert alert-warning noprint small">{{ __('exam_templates.sample_notice') }}</div>@endif
    @forelse ($items as $item)
        @php
            $student = $item['student'];
            $data = $item['data'];
            $cols = $data['columns'];
            $rows = $item['official'];
            $withData = collect($cols)->filter(fn ($c) => $c['year'] !== '');
            $lastCol = $withData->last();
            $gradeName = $lastCol ? $lastCol['level']->name : '';
            $yearName = $lastCol ? \App\Support\Digits::toPersian($lastCol['year']) : '';
            $years = $withData->map(fn ($c) => \App\Support\Digits::toPersian($c['year']))->join('، ');
            $residence = collect([$student->village, $student->district, $student->province, $student->address])->filter()->unique()->join('، ');
            $fa = fn ($v) => \App\Support\Digits::toPersian((string) $v);
        @endphp

        {{-- ================= صفحه‌ی اول ================= --}}
        <section class="moe-sheet">
            <header class="moe-head">
                <div class="moe-head-reg">
                    <div>{{ __($m.'reg_no') }} ( <span class="moe-gap"></span> )</div>
                    <div>{{ __($m.'date') }} <span class="moe-gap"></span>/<span class="moe-gap"></span>/</div>
                    <div class="moe-form-title">{{ __($m.'title') }}</div>
                </div>
                <img src="{{ asset('assets/images/moe/emblem.png') }}" alt="" class="moe-emblem">
                <div class="moe-head-lines">
                    @foreach ($stateLines as $line)<div>{{ $line }}</div>@endforeach
                    <div>{{ __($m.'directorate', ['province' => $province ?: ' ']) }}</div>
                    <div>{{ __($m.'office') }}</div>
                </div>
                <img src="{{ asset('assets/images/moe/moe-logo.jpg') }}" alt="" class="moe-logo">
                <div class="moe-photo">
                    @if ($student->exists && $student->photo_path)
                        <img src="{{ route('students.photo', $student) }}" alt="">
                    @else
                        {{ __($m.'photo') }}
                    @endif
                </div>
            </header>

            {{-- بخش اول --}}
            <table class="moe-table">
                <tr class="moe-bar"><th colspan="9"><span class="moe-bar-part">{{ __($m.'part_1') }}</span>{{ __($m.'part_1_title') }}</th></tr>
                <tr class="moe-sub"><th colspan="5">{{ __($m.'student_info') }}</th><th colspan="4">{{ __($m.'school_info') }}</th></tr>
                <tr>
                    <th class="moe-lbl">{{ __($m.'name') }}</th><td class="moe-val">{{ $student->full_name }}</td>
                    <th class="moe-lbl">{{ __($m.'grandfather') }}</th><td class="moe-val">{{ $student->grandfather_name }}</td>
                    <th class="moe-lbl">{{ __($m.'residence') }}</th>
                    <th class="moe-lbl">{{ __($m.'school_name') }}</th><th class="moe-lbl">{{ __($m.'grade') }}</th><th class="moe-lbl">{{ __($m.'year') }}</th><th class="moe-lbl">{{ __($m.'admission_no') }}</th>
                </tr>
                <tr class="moe-tall">
                    <th class="moe-lbl">{{ __($m.'father') }}</th><td class="moe-val">{{ $student->father_name }}</td>
                    <th class="moe-lbl">{{ __($m.'tazkira') }}</th><td class="moe-val">{{ $fa($student->tazkira_no) }}</td>
                    <td class="moe-val small">{{ $residence }}</td>
                    <td class="moe-val">{{ $schoolName }}</td><td class="moe-val">{{ $gradeName }}</td><td class="moe-val">{{ $yearName }}</td><td class="moe-val">{{ $fa($student->admission_no) }}</td>
                </tr>
                <tr>
                    <td colspan="9" class="moe-request">
                        <div class="fw-bold">{{ __($m.'request') }}</div>
                        <div class="moe-request-sign">{{ __($m.'request_sign') }} <span class="moe-dots"></span> {{ __($m.'date') }} <span class="moe-gap"></span>/<span class="moe-gap"></span>/</div>
                    </td>
                </tr>
            </table>

            {{-- بخش دوم: نمرات سه ساله --}}
            <table class="moe-table moe-marks">
                <tr class="moe-bar"><th colspan="{{ 3 + count($cols) * 2 }}"><span class="moe-bar-part">{{ __($m.'part_2') }}</span>{{ __($m.'part_2_title', ['school' => $schoolName]) }}</th></tr>
                <tr>
                    <th rowspan="4" class="moe-no"><span class="moe-vertical">{{ __($m.'number') }}</span></th>
                    <th rowspan="4" class="moe-subj-head">{{ __($m.'subjects_head') }}</th>
                    <th colspan="{{ count($cols) * 2 }}" class="moe-sub">{{ __($m.'marks_title') }}</th>
                    <th rowspan="4" class="moe-remarks-head">{{ __($m.'remarks') }}</th>
                </tr>
                <tr>@foreach ($cols as $c)<th colspan="2" class="moe-yg">{{ __($m.'year_col', ['year' => $c['year'] !== '' ? $fa($c['year']) : ' ']) }}</th>@endforeach</tr>
                <tr>@foreach ($cols as $c)<th colspan="2" class="moe-yg">{{ __($m.'grade_col', ['grade' => preg_replace('/^صنف\s+/u', '', $c['level']->name)]) }}</th>@endforeach</tr>
                <tr>@foreach ($cols as $c)<th class="moe-fig-h">{{ __($m.'in_figures') }}</th><th class="moe-words-h">{{ __($m.'in_words') }}</th>@endforeach</tr>
                @foreach ($rows as $r)
                    <tr>
                        <td class="moe-no">{{ $fa($r['no']) }}</td>
                        <td class="moe-subj">{{ $r['label'] }}</td>
                        @foreach ($r['marks'] as $mark)
                            <td class="moe-fig">{{ $fig($mark) }}</td><td class="moe-words">{{ $words($mark) }}</td>
                        @endforeach
                        <td class="moe-remark"></td>
                    </tr>
                @endforeach
                <tr class="moe-foot">
                    <th colspan="2">{{ __($m.'total') }}</th>
                    @foreach ($cols as $c)<td class="moe-fig">{{ $fig($c['total']) }}</td><td class="moe-words">{{ $words($c['total']) }}</td>@endforeach
                    <td></td>
                </tr>
                <tr class="moe-foot">
                    <th colspan="2">{{ __($m.'result') }}</th>
                    @foreach ($cols as $c)
                        <td colspan="2">{{ $c['result'] ? \App\Enums\FinalResult::tryFrom($c['result'])?->label() : ($c['source'] === 'pending' ? __('transcripts.pending') : '') }}</td>
                    @endforeach
                    <td></td>
                </tr>
                <tr class="moe-foot">
                    <th colspan="2">{{ __($m.'degree') }}</th>
                    @foreach ($cols as $c)<td colspan="2">{{ $c['grade'] ? __('exams.grades.'.$c['grade']) : '' }}</td>@endforeach
                    <td></td>
                </tr>
                <tr class="moe-signrow">
                    <th colspan="2">{{ __($m.'sign_stamp') }}</th>
                    @foreach ($cols as $c)<td colspan="2"></td>@endforeach
                    <td></td>
                </tr>
            </table>
        </section>

        {{-- ================= صفحه‌ی دوم ================= --}}
        <section class="moe-sheet moe-sheet-2">
            <table class="moe-table moe-flow">
                <colgroup><col class="moe-c-time"><col class="moe-c-date"><col class="moe-c-date"><col></colgroup>
                {{-- تصدیق مکتب --}}
                <tr class="moe-sub"><th colspan="4">{{ __($m.'school_confirm') }}</th></tr>
                <tr class="moe-block">
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'day') }}</span></td><td></td><td></td>
                    <td class="moe-main">
                        <div class="fw-bold mb-2">{{ __($m.'confirm_to', ['province' => $province ?: ' ']) }}</div>
                        <p class="moe-letter">{{ __($m.'confirm_text', [
                            'name' => $student->full_name, 'father' => $student->father_name, 'grade' => $gradeName, 'year' => $yearName,
                            'admission' => $fa($student->admission_no), 'school' => $schoolName, 'years' => $years,
                        ]) }}</p>
                        <div class="moe-signs"><span>{{ __($m.'sign_academic') }}</span><span>{{ __($m.'sign_principal') }}</span></div>
                    </td>
                </tr>
                <tr class="moe-block-sm">
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'time') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'received_on') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'issued_no') }}</span></td>
                    <td class="moe-main text-center"><div class="fw-bold">{{ __($m.'approval') }}</div><div class="mt-3">{{ __($m.'sign_district') }}</div></td>
                </tr>

                {{-- بخش سوم: ریاست معارف --}}
                <tr class="moe-bar"><th colspan="4"><span class="moe-bar-part">{{ __($m.'part_3') }}</span>{{ __($m.'part_3_title') }}</th></tr>
                <tr class="moe-sub"><th colspan="4">{{ __($m.'quality') }}</th></tr>
                <tr class="moe-block">
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'same_day') }}</span></td><td></td><td></td>
                    <td class="moe-main"><div class="fw-bold">{{ __($m.'opinion') }}</div><div class="moe-sign-center">{{ __($m.'sign_quality') }}</div></td>
                </tr>
                <tr class="moe-label-row">
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'time') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'received_on') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'issued_on') }}</span></td>
                    <td></td>
                </tr>
                <tr class="moe-sub"><th colspan="4">{{ __($m.'education') }}</th></tr>
                <tr class="moe-block">
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'same_time') }}</span></td><td></td><td></td>
                    <td class="moe-main">
                        <div class="fw-bold mb-2">{{ __($m.'education_to') }}</div>
                        <p class="moe-letter">{{ __($m.'education_text', ['name' => $student->full_name, 'father' => $student->father_name, 'grandfather' => $student->grandfather_name ?: ' ', 'grade' => $gradeName]) }}</p>
                        <div class="moe-signs"><span>{{ __($m.'sign_general') }}</span><span>{{ __($m.'sign_head') }}</span></div>
                    </td>
                </tr>
                <tr class="moe-label-row">
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'time') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'incoming_no') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'issued_no') }}</span></td>
                    <td></td>
                </tr>

                {{-- بخش چهارم --}}
                <tr class="moe-bar"><th colspan="4"><span class="moe-bar-part">{{ __($m.'part_4') }}</span>{{ __($m.'part_4_title') }}</th></tr>
                <tr>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'same_day') }}</span><br><span class="moe-vertical">{{ __($m.'time') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'incoming_no') }}</span></td>
                    <td class="moe-side"><span class="moe-vertical">{{ __($m.'action_date') }}</span></td>
                    <td class="p-0">
                        <table class="moe-actions">
                            <tr><th>{{ __($m.'actions') }}</th><th class="moe-check">✓</th><td rowspan="2" class="moe-sign-cell">{{ __($m.'sign_expert') }}</td></tr>
                            <tr><td>{{ __($m.'action_1') }}</td><td class="moe-check"><span class="moe-box"></span></td></tr>
                            <tr><td>{{ __($m.'action_2') }}</td><td class="moe-check"><span class="moe-box"></span></td><td rowspan="2" class="moe-sign-cell">{{ __($m.'sign_policy') }}</td></tr>
                            <tr><td>{{ __($m.'action_3') }}</td><td class="moe-check"><span class="moe-box"></span></td></tr>
                        </table>
                    </td>
                </tr>
            </table>
            <p class="moe-note">{{ __($m.'note') }}</p>
        </section>
    @empty
        <div class="text-center text-secondary py-5">{{ __('transcripts.no_match') }}</div>
    @endforelse
@endsection
