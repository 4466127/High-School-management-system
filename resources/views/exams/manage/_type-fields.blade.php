{{-- خانه‌های نوع امتحان. $type (یا null)، $prefix، $useOld --}}
@php
    $checked = fn ($field, $default) => $useOld ? (bool) old($field, $default) : (bool) $default;
@endphp
<div class="row">
    <x-form.input class="col-12 mb-3" name="name" :label="__('exams.type_name')" :value="$type?->name" required maxlength="100" :placeholder="__('exams.type_name_ph')" :use-old="$useOld" id="{{ $prefix }}name" />
    <x-form.input class="col-md-4 mb-3" name="default_max_mark" type="number" step="0.5" min="1" max="999" :label="__('exams.default_max_mark')" :value="$type ? \App\Services\MarkService::format($type->default_max_mark) : null" required :use-old="$useOld" id="{{ $prefix }}max" />
    <x-form.input class="col-md-4 mb-3" name="default_weight" type="number" step="0.5" min="0" max="999" :label="__('exams.default_weight')" :value="$type ? \App\Services\MarkService::format($type->default_weight) : 0" required :use-old="$useOld" id="{{ $prefix }}weight" />
    <x-form.input class="col-md-4 mb-3" name="sort_order" type="number" min="1" max="99" :label="__('exams.sort_order')" :value="$type?->sort_order ?? 1" required :use-old="$useOld" id="{{ $prefix }}sort" />
</div>
<div class="form-check mb-2">
    <input type="hidden" name="counts_in_final" value="0">
    <input class="form-check-input" type="checkbox" name="counts_in_final" value="1" id="{{ $prefix }}counts" @checked($checked('counts_in_final', $type?->counts_in_final ?? true))>
    <label class="form-check-label" for="{{ $prefix }}counts">{{ __('exams.counts_in_final') }}</label>
</div>
<div class="form-check">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $prefix }}active" @checked($checked('is_active', $type?->is_active ?? true))>
    <label class="form-check-label" for="{{ $prefix }}active">{{ __('exams.type_active') }}</label>
</div>
