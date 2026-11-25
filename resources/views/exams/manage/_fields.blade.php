{{-- خانه‌های امتحان (ساخت در پنجره، ویرایش در صفحه). $exam (اختیاری)، $types، $useOld --}}
@php
    $exam = $exam ?? null;
    $pick = fn ($field, $default) => $useOld ? old($field, $default) : $default;
    $counts = (string) $pick('counts_in_final', $exam ? ($exam->counts_in_final ? '1' : '0') : '');
    $typeId = (string) $pick('exam_type_id', $exam?->exam_type_id);
@endphp
<div class="row">
    <div class="col-12 mb-3">
        <label for="{{ $prefix }}type" class="form-label">{{ __('exams.type') }} <span class="text-danger">*</span></label>
        <select id="{{ $prefix }}type" name="exam_type_id" class="form-select" required>
            <option value="">{{ __('exams.choose_type') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected($typeId === (string) $type->id)>
                    {{ $type->name }} ({{ __('exams.type_defaults', ['max' => \App\Support\Digits::toPersian(\App\Services\MarkService::format($type->default_max_mark))]) }})
                </option>
            @endforeach
        </select>
        @if ($useOld)@error('exam_type_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endif
    </div>
    <x-form.input class="col-12 mb-3" name="name" :label="__('exams.name')" :value="$exam?->name" required maxlength="150" :placeholder="__('exams.name_ph')" :use-old="$useOld" />
    <x-jalali-date class="col-md-6 mb-3" name="start_date" :label="__('exams.start_date')" :value="$exam?->start_date" :use-old="$useOld" />
    <x-jalali-date class="col-md-6 mb-3" name="end_date" :label="__('exams.end_date')" :value="$exam?->end_date" :use-old="$useOld" />
    <x-form.input class="col-md-6 mb-3" name="max_mark" type="number" step="0.5" min="1" max="999" :label="__('exams.max_mark')" :value="$exam ? \App\Services\MarkService::format($exam->max_mark) : null" :placeholder="__('exams.from_type')" :use-old="$useOld" />
    <div class="col-md-6 mb-3">
        <label for="{{ $prefix }}counts" class="form-label">{{ __('exams.counts_in_final') }}</label>
        <select id="{{ $prefix }}counts" name="counts_in_final" class="form-select">
            @unless ($exam)<option value="" @selected($counts === '')>{{ __('exams.from_type') }}</option>@endunless
            <option value="1" @selected($counts === '1')>{{ __('app.yes') }}</option>
            <option value="0" @selected($counts === '0')>{{ __('app.no') }}</option>
        </select>
    </div>
</div>
<div class="form-text">{{ __('exams.fields_hint') }}</div>
