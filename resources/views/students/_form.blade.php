{{-- فرم مشترک ثبت و ویرایش دانش‌آموز. $student؛ هنگام ثبت: $nextAdmissionNo --}}
@php
    $creating = ! $student->exists;
    $provinces = array_combine(config('afghanistan.provinces'), config('afghanistan.provinces'));
@endphp

<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-id-card" aria-hidden="true"></i> {{ __('students.section_identity') }}</div>
    <div class="card-body">
        <div class="row">
            <x-form.input class="col-md-3 mb-3" name="admission_no" :label="__('students.admission_no')" :value="$student->admission_no"
                          :required="! $creating" ltr maxlength="30"
                          :placeholder="$creating ? __('students.ph.admission_no_auto') : __('students.ph.admission_no')"
                          :help="$creating ? __('students.admission_no_auto', ['no' => \App\Support\Digits::toPersian($nextAdmissionNo)]) : null" />
            <x-form.input class="col-md-3 mb-3" name="first_name" :label="__('students.first_name')" :value="$student->first_name" required maxlength="100"
                          :placeholder="__('students.ph.first_name')" :help="__('students.help.first_name')" />
            <x-form.input class="col-md-3 mb-3" name="last_name" :label="__('students.last_name')" :value="$student->last_name" maxlength="100"
                          :placeholder="__('students.ph.last_name')" />
            <x-form.select class="col-md-3 mb-3" name="gender" :label="__('students.gender')" :options="\App\Enums\Gender::studentOptions()"
                           :value="$student->gender" :placeholder="__('students.ph.gender')" required />
            <x-form.input class="col-md-3 mb-3" name="father_name" :label="__('students.father_name')" :value="$student->father_name" required maxlength="100"
                          :placeholder="__('students.ph.father_name')" />
            <x-form.input class="col-md-3 mb-3" name="grandfather_name" :label="__('students.grandfather_name')" :value="$student->grandfather_name" maxlength="100"
                          :placeholder="__('students.ph.grandfather_name')" />
            <x-jalali-date class="col-md-3 mb-3" name="birth_date" :label="__('students.birth_date')" :value="$student->birth_date"
                           :help="__('students.help.birth_date')" />
            <x-form.input class="col-md-3 mb-3" name="tazkira_no" :label="__('students.tazkira_no')" :value="$student->tazkira_no" ltr maxlength="50"
                          :placeholder="__('students.ph.tazkira_no')" />
        </div>
    </div>
</div>

<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ __('students.section_contact') }}</div>
    <div class="card-body">
        <div class="row">
            <x-form.input class="col-md-3 mb-3" name="phone" :label="__('students.phone')" :value="$student->phone" type="tel" ltr inputmode="tel" maxlength="30"
                          :placeholder="__('students.ph.phone')" :help="__('students.help.phone')" />
            <x-form.select class="col-md-3 mb-3" name="province" :label="__('students.province')" :options="$provinces" :value="$student->province"
                           :placeholder="__('students.ph.province')" />
            <x-form.input class="col-md-3 mb-3" name="district" :label="__('students.district')" :value="$student->district" maxlength="100"
                          :placeholder="__('students.ph.district')" />
            <x-form.input class="col-md-3 mb-3" name="village" :label="__('students.village')" :value="$student->village" maxlength="150"
                          :placeholder="__('students.ph.village')" />
            <x-form.input class="col-12 mb-3" name="address" :label="__('students.address')" :value="$student->address" maxlength="500"
                          :placeholder="__('students.ph.address')" />
        </div>
    </div>
</div>

<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-school" aria-hidden="true"></i> {{ __('students.section_school') }}</div>
    <div class="card-body">
        <div class="row">
            <x-jalali-date class="col-md-3 mb-3" name="admission_date" :label="__('students.admission_date')" :value="$student->admission_date"
                           :help="__('students.help.admission_date')" />
            <div class="col-md-5 mb-3">
                <x-form.input class="mb-1" name="photo" :label="__('students.photo')" type="file" accept="image/jpeg,image/png,image/webp" :help="__('students.photo_hint')" />
                @if ($student->photo_path)
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <x-avatar :src="route('students.photo', $student)" size="48" />
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo" @checked(old('remove_photo'))>
                            <label class="form-check-label" for="remove_photo">{{ __('students.remove_photo') }}</label>
                        </div>
                    </div>
                @endif
            </div>
            <x-form.textarea class="col-12 mb-3" name="notes" :label="__('students.notes')" :value="$student->notes" rows="2" maxlength="2000"
                             :placeholder="__('students.ph.notes')" />
        </div>
    </div>
</div>

@if ($creating)
    <div class="card form-section mb-3">
        <div class="card-header"><i class="fa-solid fa-people-roof" aria-hidden="true"></i> {{ __('students.section_guardian') }} <span class="text-secondary fw-normal small">({{ __('app.optional') }})</span></div>
        <div class="card-body">
            <p class="form-text mt-0">{{ __('students.section_guardian_hint') }} {{ __('students.guardian_link_hint') }}</p>
            <div class="row">
                <x-form.input class="col-md-4 mb-3" name="guardian[name]" :label="__('students.guardian_name')" maxlength="150" :placeholder="__('students.ph.guardian_name')" />
                <x-form.input class="col-md-2 mb-3" name="guardian[relation]" :label="__('students.guardian_relation')" list="guardian-relations" maxlength="50" :placeholder="__('students.ph.guardian_relation')" />
                <x-form.input class="col-md-3 mb-3" name="guardian[phone]" :label="__('students.guardian_phone')" type="tel" ltr inputmode="tel" maxlength="30" :placeholder="__('students.ph.guardian_phone')" />
                <x-form.input class="col-md-3 mb-3" name="guardian[phone2]" :label="__('students.guardian_phone2')" type="tel" ltr inputmode="tel" maxlength="30" :placeholder="__('students.ph.guardian_phone2')" />
                <x-form.input class="col-md-4 mb-3" name="guardian[occupation]" :label="__('students.guardian_occupation')" maxlength="150" :placeholder="__('students.ph.guardian_occupation')" />
            </div>
        </div>
    </div>
    @include('students.partials.relations-datalist')
@endif
