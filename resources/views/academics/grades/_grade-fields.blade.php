{{-- $grade (GradeLevel|null), $own, $prefix, $order --}}
<div class="row">
    <x-form.input class="col-8 mb-3" :id="$prefix.'_name'" name="name" :label="__('academics.grade_name')" :value="$grade?->name" required maxlength="50" :placeholder="__('academics.grade_name_ph')" :use-old="$own" />
    <x-form.input class="col-4 mb-3" :id="$prefix.'_order'" name="sort_order" type="number" min="1" max="999" :label="__('academics.grade_order')" :value="$order" required :use-old="$own" />
</div>
<p class="form-text mt-0">{{ __('academics.grade_order_hint') }}</p>
<input type="hidden" name="is_active" value="0">
<div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $prefix }}_active" @checked($own ? old('is_active') : ($grade?->is_active ?? true))>
    <label class="form-check-label" for="{{ $prefix }}_active">{{ __('academics.grade_active') }}</label>
</div>
