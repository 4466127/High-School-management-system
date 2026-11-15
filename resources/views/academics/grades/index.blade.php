@extends('layouts.app', ['title' => __('academics.grades_subjects')])

@section('content')
    <h1 class="page-title">{{ __('academics.grades_subjects') }}</h1>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#grades-pane" type="button" role="tab">{{ __('academics.grades') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#subjects-pane" type="button" role="tab">{{ __('academics.subjects') }}</button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- صنف‌ها --}}
        <div class="tab-pane fade show active" id="grades-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <p class="form-text m-0">{{ __('academics.grade_order_hint') }}</p>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#gradeAdd"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('academics.grade_add') }}</button>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('academics.grade_order') }}</th>
                            <th>{{ __('academics.grade_name') }}</th>
                            <th>{{ __('academics.subjects') }}</th>
                            <th class="text-end">{{ __('app.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($grades as $grade)
                            <tr @class(['opacity-50' => ! $grade->is_active])>
                                <td>@fa($grade->sort_order)</td>
                                <td class="fw-medium">
                                    {{ $grade->name }}
                                    @unless ($grade->is_active)<span class="badge text-bg-secondary">{{ __('academics.inactive') }}</span>@endunless
                                </td>
                                <td>
                                    @if ($grade->subjects_count)
                                        <span class="badge text-bg-light border">{{ __('academics.subjects_count', ['count' => \App\Support\Digits::toPersian($grade->subjects_count)]) }}</span>
                                    @else
                                        <span class="text-warning-emphasis small">{{ __('academics.no_grade_subjects') }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('academics.grades.subjects', $grade) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-book-open" aria-hidden="true"></i> {{ __('academics.manage_subjects') }}</a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#gradeEdit{{ $grade->id }}">{{ __('app.edit') }}</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- مضامین --}}
        <div class="tab-pane fade" id="subjects-pane" role="tabpanel">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#subjectAdd"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('academics.subject_add') }}</button>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('academics.subject_name') }}</th>
                            <th>{{ __('academics.subject_code') }}</th>
                            <th>{{ __('academics.grades') }}</th>
                            <th class="text-end">{{ __('app.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($subjects as $subject)
                            <tr @class(['opacity-50' => ! $subject->is_active])>
                                <td class="fw-medium">
                                    {{ $subject->name }}
                                    @unless ($subject->is_active)<span class="badge text-bg-secondary">{{ __('academics.inactive') }}</span>@endunless
                                </td>
                                <td dir="ltr" class="text-end small">{{ $subject->code }}</td>
                                <td class="small">{{ __('academics.used_in_grades', ['count' => \App\Support\Digits::toPersian($subject->grade_levels_count)]) }}</td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#subjectEdit{{ $subject->id }}">{{ __('app.edit') }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-3">{{ __('app.no_records') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- پنجره‌های صنف --}}
    <x-modal-form id="gradeAdd" :title="__('academics.grade_add')" :action="route('academics.grades.store')" :submit="__('app.add')">
        @include('academics.grades._grade-fields', ['grade' => null, 'own' => old('_modal') === 'gradeAdd', 'prefix' => 'gradeAdd', 'order' => $nextSortOrder])
    </x-modal-form>
    @foreach ($grades as $grade)
        @php($modalId = 'gradeEdit'.$grade->id)
        <x-modal-form :id="$modalId" :title="__('academics.grade_edit')" :action="route('academics.grades.update', $grade)" method="PUT" :submit="__('app.save')">
            @include('academics.grades._grade-fields', ['grade' => $grade, 'own' => old('_modal') === $modalId, 'prefix' => $modalId, 'order' => $grade->sort_order])
        </x-modal-form>
    @endforeach

    {{-- پنجره‌های مضمون --}}
    <x-modal-form id="subjectAdd" :title="__('academics.subject_add')" :action="route('academics.subjects.store')" :submit="__('app.add')">
        @include('academics.grades._subject-fields', ['subject' => null, 'own' => old('_modal') === 'subjectAdd', 'prefix' => 'subjectAdd'])
    </x-modal-form>
    @foreach ($subjects as $subject)
        @php($modalId = 'subjectEdit'.$subject->id)
        <x-modal-form :id="$modalId" :title="__('academics.subject_edit')" :action="route('academics.subjects.update', $subject)" method="PUT" :submit="__('app.save')">
            @include('academics.grades._subject-fields', ['subject' => $subject, 'own' => old('_modal') === $modalId, 'prefix' => $modalId])
        </x-modal-form>
    @endforeach
@endsection
