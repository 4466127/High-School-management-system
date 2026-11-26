@extends('layouts.app', ['title' => __('exam_templates.title')])

@php($t = 'exam_templates.')

@section('content')
    <h1 class="page-title">{{ __($t.'title') }}</h1>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'intro') }}</div>

    <form method="POST" action="{{ route('exams.templates.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="card form-section mb-3" id="reportcard">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="flex-grow-1"><i class="fa-solid fa-id-card" aria-hidden="true"></i> {{ __($t.'section_reportcard') }}</span>
                <a href="{{ route('exams.templates.preview', ['type' => 'reportcard']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ __($t.'preview') }}</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <x-form.input class="col-md-6 mb-3" name="reportcard_title" :label="__($t.'reportcard_title')" :value="$values['reportcard_title']" maxlength="150" :placeholder="__($t.'reportcard_title_ph')" :help="__($t.'title_hint')" />
                    <x-form.input class="col-md-6 mb-3" name="reportcard_subtitle" :label="__($t.'subtitle')" :value="$values['reportcard_subtitle']" maxlength="150" :placeholder="__($t.'subtitle_ph')" />
                </div>
                <div class="fw-medium mb-2">{{ __($t.'columns') }}</div>
                <div class="row mb-2">
                    @foreach (['reportcard_show_exams', 'reportcard_show_grade', 'reportcard_show_attendance', 'reportcard_show_rules', 'reportcard_show_remarks', 'reportcard_show_stamp'] as $key)
                        <div class="col-md-6 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" value="1" id="{{ $key }}" @checked(old($key, $values[$key] === '1'))>
                                <label class="form-check-label" for="{{ $key }}">{{ __($t.$key) }}</label>
                            </div>
                            <div class="form-text mt-0">{{ __($t.$key.'_hint') }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="row">
                    <x-form.input class="col-md-4 mb-3" name="reportcard_signature_1" :label="__($t.'signature_1')" :value="$values['reportcard_signature_1']" maxlength="100" :placeholder="__($t.'signature_1_ph')" :help="__($t.'signature_1_hint')" />
                    <x-form.input class="col-md-4 mb-3" name="reportcard_signature_2" :label="__($t.'signature_2')" :value="$values['reportcard_signature_2']" maxlength="100" :placeholder="__($t.'signature_2_ph')" :help="__($t.'signature_last_hint')" />
                    <x-form.input class="col-md-4 mb-3" name="reportcard_signature_3" :label="__($t.'signature_3')" :value="$values['reportcard_signature_3']" maxlength="100" :placeholder="__($t.'signature_3_ph')" :help="__($t.'signature_empty_hint')" />
                </div>
                <x-form.textarea name="reportcard_footer" :label="__($t.'footer')" :value="$values['reportcard_footer']" rows="2" maxlength="500" :placeholder="__($t.'footer_ph')" :help="__($t.'footer_hint')" />
            </div>
        </div>

        <div class="card form-section mb-3" id="transcript">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="flex-grow-1"><i class="fa-solid fa-scroll" aria-hidden="true"></i> {{ __($t.'section_transcript') }}</span>
                <a href="{{ route('exams.templates.preview', ['type' => 'transcript']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ __($t.'preview') }}</a>
            </div>
            <div class="card-body">
                <x-form.input name="transcript_title" :label="__($t.'transcript_title')" :value="$values['transcript_title']" maxlength="150" :placeholder="__('transcripts.default_title')" :help="__($t.'title_hint')" />
                <div class="mb-3">
                    <div class="form-label">{{ __($t.'transcript_grades') }}</div>
                    @php($chosen = array_map('intval', old('transcript_grades', $transcriptGrades)))
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($grades as $grade)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="transcript_grades[]" value="{{ $grade->id }}" id="tg{{ $grade->id }}" @checked(in_array($grade->id, $chosen, true))>
                                <label class="form-check-label" for="tg{{ $grade->id }}">{{ $grade->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">{{ __($t.'transcript_grades_hint') }}</div>
                    @error('transcript_grades')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <x-form.input class="col-md-6 mb-3" name="transcript_signature_1" :label="__($t.'signature_1')" :value="$values['transcript_signature_1']" maxlength="100" :placeholder="__($t.'signature_2_ph')" :help="__($t.'transcript_signature_hint')" />
                    <x-form.input class="col-md-6 mb-3" name="transcript_signature_2" :label="__($t.'signature_2')" :value="$values['transcript_signature_2']" maxlength="100" :placeholder="__($t.'signature_3_ph')" :help="__($t.'signature_empty_hint')" />
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="transcript_show_stamp" value="1" id="transcript_show_stamp" @checked(old('transcript_show_stamp', $values['transcript_show_stamp'] === '1'))>
                    <label class="form-check-label" for="transcript_show_stamp">{{ __($t.'reportcard_show_stamp') }}</label>
                </div>
                <x-form.textarea name="transcript_footer" :label="__($t.'footer')" :value="$values['transcript_footer']" rows="2" maxlength="500" :placeholder="__($t.'transcript_footer_ph')" :help="__($t.'footer_hint')" />
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        <span class="form-text ms-2">{{ __($t.'save_then_preview') }}</span>
    </form>
@endsection
