@extends('layouts.app', ['title' => __('academics.promotion')])

@section('content')
    <h1 class="page-title">{{ __('academics.promotion') }}</h1>
    <div class="alert alert-info small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('academics.promotion_intro') }}</div>

    @if (! $currentYear || $years->isEmpty())
        <div class="card card-body text-center py-5">
            <div class="text-secondary mb-3">{{ $currentYear ? __('academics.promotion_need_years') : __('academics.no_year_hint') }}</div>
            @can('academics.manage')
                <div><a href="{{ route('academics.years.create') }}" class="btn btn-primary">{{ __('academics.year_create') }}</a></div>
            @endcan
        </div>
    @else
        {{-- گام ۱: انتخاب بخش و سال مقصد --}}
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="section" class="form-label">{{ __('academics.source_section') }} ({{ __('academics.year') }} @fa($currentYear->name))</label>
                    <select id="section" name="section" class="form-select">
                        <option value="">{{ __('academics.choose_section') }}</option>
                        @foreach ($sourceSections as $s)
                            <option value="{{ $s->id }}" @selected($section && $section->id === $s->id)>{{ $s->label }} — {{ __('academics.students_count', ['count' => \App\Support\Digits::toPersian($s->active_enrollments_count)]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="target_year" class="form-label">{{ __('academics.target_year') }}</label>
                    <select id="target_year" name="target_year" class="form-select">
                        @foreach ($years as $y)
                            <option value="{{ $y->id }}" @selected($targetYear && $targetYear->id === $y->id)>{{ __('academics.year') }} @fa($y->name)</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary w-100">{{ __('academics.load_students') }}</button>
                </div>
            </div>
        </form>

        {{-- گام ۲: تصمیم برای هر دانش‌آموز --}}
        @isset($enrollments)
            <form method="POST" action="{{ route('academics.promotion.store') }}" class="card form-section" data-confirm="{{ __('academics.confirm_promotion') }}" novalidate>
                @csrf
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <input type="hidden" name="target_year_id" value="{{ $targetYear->id }}">
                <div class="card-header"><i class="fa-solid fa-arrow-up-wide-short" aria-hidden="true"></i> {{ $section->label }} → {{ __('academics.year') }} @fa($targetYear->name)</div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger small mb-3">
                            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif

                    <div class="row">
                        @if ($nextGrade)
                            <div class="col-md-4 mb-3">
                                <label for="promote_section_id" class="form-label">{{ __('academics.promote_to', ['grade' => $nextGrade->name]) }}</label>
                                @if ($promoteSections->isEmpty())
                                    <div class="text-danger small">{{ __('academics.no_target_sections') }}</div>
                                @else
                                    <select id="promote_section_id" name="promote_section_id" class="form-select">
                                        @foreach ($promoteSections as $s)
                                            <option value="{{ $s->id }}" @selected((string) old('promote_section_id') === (string) $s->id || (! old('promote_section_id') && $s->name === $section->name && $s->shift === $section->shift))>{{ $s->label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        @else
                            <div class="col-md-4 mb-3"><div class="alert alert-primary small mb-0">{{ __('academics.last_grade_note') }}</div></div>
                        @endif
                        <div class="col-md-4 mb-3">
                            <label for="repeat_section_id" class="form-label">{{ __('academics.repeat_in', ['grade' => $section->gradeLevel->name]) }}</label>
                            @if ($repeatSections->isEmpty())
                                <div class="text-secondary small">{{ __('academics.no_target_sections') }}</div>
                            @else
                                <select id="repeat_section_id" name="repeat_section_id" class="form-select">
                                    @foreach ($repeatSections as $s)
                                        <option value="{{ $s->id }}" @selected((string) old('repeat_section_id') === (string) $s->id)>{{ $s->label }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <x-jalali-date class="col-md-4 mb-3" name="date" :label="__('academics.promotion_date')" :value="$targetYear->start_date" />
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 60px">{{ __('academics.roll_no') }}</th>
                                <th>{{ __('students.full_name') }}</th>
                                <th>{{ __('students.father_name') }}</th>
                                <th>{{ __('academics.decision') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php($default = $nextGrade ? 'promote' : 'graduate')
                            @forelse ($enrollments as $enrollment)
                                @php($choice = old('decisions.'.$enrollment->id, $default))
                                <tr>
                                    <td>@fa($enrollment->roll_no)</td>
                                    <td class="fw-medium">{{ $enrollment->student->full_name }}</td>
                                    <td>{{ $enrollment->student->father_name }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm decision-group" role="group">
                                            @foreach (array_filter(['promote' => $nextGrade !== null, 'graduate' => $nextGrade === null, 'repeat' => true, 'skip' => true]) as $value => $show)
                                                <input type="radio" class="btn-check" name="decisions[{{ $enrollment->id }}]" id="d{{ $enrollment->id }}{{ $value }}" value="{{ $value }}" @checked($choice === $value)>
                                                <label class="btn btn-outline-{{ ['promote' => 'success', 'graduate' => 'primary', 'repeat' => 'warning', 'skip' => 'secondary'][$value] }}" for="d{{ $enrollment->id }}{{ $value }}">{{ __('academics.decision_'.$value) }}</label>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary py-3">{{ __('academics.no_students_in_section') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($enrollments->isNotEmpty())
                    <div class="card-footer bg-transparent">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('academics.run_promotion') }}</button>
                    </div>
                @endif
            </form>
        @endisset
    @endif
@endsection
