{{-- خانه‌های فرم سرپرست. $g (Guardian|null)، $relation، $primary، $own (همین فرم فرستاده شده؟)، $prefix (برای id یکتا) --}}
<div class="row">
    <x-form.input class="col-md-7 mb-3" :id="$prefix.'_name'" name="name" :label="__('students.guardian_name')" :value="$g?->name" required maxlength="150" :use-old="$own" />
    <x-form.input class="col-md-5 mb-3" :id="$prefix.'_relation'" name="relation" :label="__('students.guardian_relation')" :value="$relation" required list="guardian-relations" maxlength="50" :use-old="$own" />
    <x-form.input class="col-md-6 mb-3" :id="$prefix.'_phone'" name="phone" :label="__('students.guardian_phone')" :value="$g?->phone" type="tel" ltr inputmode="tel" maxlength="30" :use-old="$own" />
    <x-form.input class="col-md-6 mb-3" :id="$prefix.'_phone2'" name="phone2" :label="__('students.guardian_phone2')" :value="$g?->phone2" type="tel" ltr inputmode="tel" maxlength="30" :use-old="$own" />
    <x-form.input class="col-12 mb-3" :id="$prefix.'_occupation'" name="occupation" :label="__('students.guardian_occupation')" :value="$g?->occupation" maxlength="150" :use-old="$own" />
    <x-form.input class="col-12 mb-3" :id="$prefix.'_address'" name="address" :label="__('students.guardian_address')" :value="$g?->address" maxlength="500" :use-old="$own" />
</div>
<div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="{{ $prefix }}_primary"
           @checked($own ? old('is_primary') : $primary) @disabled($g && $primary)>
    <label class="form-check-label" for="{{ $prefix }}_primary">{{ __('students.guardian_make_primary') }}</label>
</div>
