@extends('layouts.app', ['title' => __('past_results.title')])

@php
    $p = 'past_results.';
    $editing = $row !== null;
    $resultOptions = \App\Enums\FinalResult::options();
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $editing ? __($p.'edit_title') : __($p.'create_title') }} <span class="text-secondary fs-6">— {{ $student->full_name }} ({{ __('students.father_name') }}: {{ $student->father_name }})</span></h1>
        <a href="{{ route('students.show', $student) }}#history" class="btn btn-light">{{ __('app.back') }}</a>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($p.'intro') }}</div>

    @unless ($editing)
        {{-- گام ۱: انتخاب صنف ← مضامین همان صنف در جدول پایین آماده می‌شود --}}
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="pick-grade" class="form-label">{{ __($p.'step_grade') }}</label>
                    <select name="grade" id="pick-grade" class="form-select">
                        <option value="">{{ __($p.'grade_other') }}</option>
                        @foreach ($grades as $g)<option value="{{ $g->id }}" @selected($grade && $grade->id === $g->id)>{{ $g->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-7 form-text">{{ __($p.'step_grade_hint') }}</div>
            </div>
        </form>
    @endunless

    <form method="POST" action="{{ $editing ? route('student-past-results.update', $row) : route('student-past-results.store', $student) }}" class="card form-section" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row">
                @if ($editing)
                    <x-form.select class="col-md-3 mb-3" name="grade_level_id" :label="__('academics.grade')" :options="$grades->pluck('name', 'id')->all()" :value="$row->grade_level_id" :placeholder="__($p.'grade_other')" />
                @else
                    <input type="hidden" name="grade_level_id" value="{{ $grade?->id }}">
                @endif
                @if (! $grade || $editing)
                    <x-form.input class="col-md-3 mb-3" name="grade_name" :label="__($p.'grade_name')" :value="$row?->grade_level_id ? '' : $row?->grade_name" maxlength="100" :placeholder="__($p.'grade_name_ph')" :help="__($p.'grade_name_hint')" />
                @else
                    <div class="col-md-3 mb-3"><div class="form-label">{{ __('academics.grade') }}</div><div class="form-control-plaintext fw-bold">{{ $grade->name }}</div></div>
                @endif
                <x-form.input class="col-md-3 mb-3" name="year_name" :label="__($p.'year_name')" :value="$row?->year_name" required maxlength="4" ltr :placeholder="__($p.'year_name_ph')" :help="__($p.'year_name_hint')" />
                <x-form.input class="col-md-3 mb-3" name="school_name" :label="__($p.'school_name')" :value="$row?->school_name" maxlength="200" :placeholder="__($p.'school_name_ph')" :help="__($p.'school_name_hint')" />
            </div>

            <div class="fw-medium mb-1">{{ __($p.'subjects') }}</div>
            <div class="form-text mt-0 mb-2">{{ __($p.'subjects_hint') }}</div>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0" style="max-width: 560px">
                    <thead><tr><th style="width: 40px">#</th><th>{{ __('academics.subject') }}</th><th style="width: 140px">{{ __($p.'mark') }}</th></tr></thead>
                    <tbody>
                    @foreach (old('subjects', $subjects) as $i => $s)
                        <tr>
                            <td class="text-secondary">@fa($i + 1)</td>
                            <td>
                                <input type="text" name="subjects[{{ $i }}][name]" value="{{ $s['name'] ?? '' }}" maxlength="100" class="form-control form-control-sm @error('subjects.'.$i.'.name') is-invalid @enderror"
                                       placeholder="{{ ($s['name'] ?? '') === '' ? __($p.'subject_ph') : '' }}" aria-label="{{ __('academics.subject') }}">
                            </td>
                            <td>
                                <input type="text" inputmode="decimal" name="subjects[{{ $i }}][mark]" value="{{ $s['mark'] ?? '' }}" class="form-control form-control-sm ltr @error('subjects.'.$i.'.mark') is-invalid @enderror"
                                       placeholder="{{ __($p.'mark_ph') }}" aria-label="{{ __($p.'mark') }}">
                                @error('subjects.'.$i.'.mark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="fw-medium mb-1">{{ __($p.'summary') }}</div>
            <div class="form-text mt-0 mb-2">{{ __($p.'summary_hint') }}</div>
            <div class="row">
                <x-form.input class="col-md-3 mb-3" name="total" :label="__('exams.total')" :value="$row?->total" inputmode="decimal" ltr :placeholder="__($p.'auto')" />
                <x-form.input class="col-md-3 mb-3" name="average" :label="__('exams.average')" :value="$row?->average" inputmode="decimal" ltr :placeholder="__($p.'average_ph')" />
                <x-form.select class="col-md-3 mb-3" name="result" :label="__('exams.result')" :options="$resultOptions" :value="$row?->result?->value" :placeholder="__($p.'auto')" />
                <x-form.input class="col-md-3 mb-3" name="note" :label="__($p.'note')" :value="$row?->note" maxlength="500" :placeholder="__($p.'note_ph')" />
            </div>
        </div>
        <div class="card-footer bg-transparent">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
        </div>
    </form>
@endsection
