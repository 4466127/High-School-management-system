@extends('layouts.app', ['title' => $activity->exists ? __('labs.edit_activity') : __('labs.plan_activity')])

@php($t = 'labs.')

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ $activity->exists ? __($t.'edit_activity').' — '.$activity->title : __($t.'plan_activity') }}</h1>
        <a href="{{ $activity->exists ? route('labs.activities.show', $activity) : route('labs.activities.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __($t.'plan_intro') }}</div>

    @if (! $activity->exists && $assignments === [])
        <div class="alert alert-warning">{{ __($t.'no_assignments') }}</div>
    @endif
    @if ($labs === [])
        <div class="alert alert-warning">{{ __($t.'no_labs') }}</div>
    @endif

    <form method="POST" action="{{ $activity->exists ? route('labs.activities.update', $activity) : route('labs.activities.store') }}" class="card" novalidate>
        @csrf
        @if ($activity->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row">
                @if ($activity->exists)
                    <div class="col-md-6 mb-3">
                        <div class="form-label">{{ __($t.'subject_section') }}</div>
                        <div class="form-control-plaintext fw-medium">{{ $activity->subject->name }} — {{ $activity->section->gradeLevel->name }} {{ $activity->section->name }}</div>
                    </div>
                @else
                    <x-form.select class="col-md-6" name="assignment" :label="__($t.'subject_section')" :options="$assignments" required :placeholder="__($t.'choose_assignment')" :help="__($t.'help.assignment')" />
                @endif
                <x-form.select class="col-md-6" name="lab_id" :label="__($t.'lab')" :options="$labs" :value="$activity->lab_id" required :placeholder="__($t.'choose_lab')" />
                <x-jalali-date class="col-6 col-md-4" name="activity_date" :label="__($t.'date')" :value="$activity->activity_date" required />
                <x-form.select class="col-6 col-md-4" name="period_id" :label="__($t.'period')" :options="$periods" :value="$activity->period_id" :placeholder="__($t.'no_period')" :help="__($t.'help.period')" />
                <x-form.input class="col-md-4" name="max_score" type="number" min="1" max="100" :label="__($t.'max_score')" :value="$activity->max_score" :placeholder="__($t.'ph.max_score')" :help="__($t.'help.max_score')" />
                <x-form.input class="col-12" name="title" :label="__($t.'activity_title')" :value="$activity->title" required maxlength="200" :placeholder="__($t.'ph.title')" />
                <x-form.textarea class="col-md-6" name="objectives" :label="__($t.'objectives')" :value="$activity->objectives" rows="3" maxlength="1000" :placeholder="__($t.'ph.objectives')" />
                <x-form.textarea class="col-md-6" name="materials_note" :label="__($t.'materials_note')" :value="$activity->materials_note" rows="3" maxlength="1000" :placeholder="__($t.'ph.materials_note')" :help="__($t.'help.materials_note')" />
            </div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection
