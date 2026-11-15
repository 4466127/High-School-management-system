{{-- فرم مشترک ثبت و ویرایش معلم/کارمند. $member --}}
<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-id-card" aria-hidden="true"></i> {{ __('staff.section_identity') }}</div>
    <div class="card-body">
        <div class="row">
            <x-form.input class="col-md-3 mb-3" name="first_name" :label="__('staff.first_name')" :value="$member->first_name" required maxlength="100" :placeholder="__('staff.ph.first_name')" />
            <x-form.input class="col-md-3 mb-3" name="last_name" :label="__('staff.last_name')" :value="$member->last_name" maxlength="100" :placeholder="__('staff.ph.last_name')" />
            <x-form.input class="col-md-3 mb-3" name="father_name" :label="__('staff.father_name')" :value="$member->father_name" maxlength="100" :placeholder="__('staff.ph.father_name')" />
            <x-form.select class="col-md-3 mb-3" name="gender" :label="__('staff.gender')" :options="\App\Enums\Gender::staffOptions()" :value="$member->gender" :placeholder="__('staff.ph.gender')" required />
            <x-form.input class="col-md-4 mb-3" name="phone" :label="__('staff.phone')" :value="$member->phone" type="tel" ltr inputmode="tel" maxlength="30" :placeholder="__('staff.ph.phone')" />
            <x-form.input class="col-md-8 mb-3" name="address" :label="__('staff.address')" :value="$member->address" maxlength="500" :placeholder="__('staff.ph.address')" />
        </div>
    </div>
</div>

<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-briefcase" aria-hidden="true"></i> {{ __('staff.section_job') }}</div>
    <div class="card-body">
        <div class="row">
            <x-form.select class="col-md-3 mb-3" name="staff_type" :label="__('staff.staff_type')" :options="\App\Enums\StaffType::options()" :value="$member->staff_type" required />
            <x-form.input class="col-md-3 mb-3" name="position" :label="__('staff.position')" :value="$member->position" maxlength="150" :placeholder="__('staff.ph.position')" />
            <x-jalali-date class="col-md-3 mb-3" name="hire_date" :label="__('staff.hire_date')" :value="$member->hire_date" />
            <x-form.select class="col-md-3 mb-3" name="education" :label="__('staff.education')" :options="\App\Enums\EducationLevel::options()" :value="$member->education" :placeholder="__('staff.ph.education')" />
            <x-form.input class="col-md-6 mb-3" name="specialization" :label="__('staff.specialization')" :value="$member->specialization" maxlength="150" :placeholder="__('staff.ph.specialization')" />
        </div>
    </div>
</div>

<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-camera" aria-hidden="true"></i> {{ __('staff.section_other') }}</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <x-form.input class="mb-1" name="photo" :label="__('students.photo')" type="file" accept="image/jpeg,image/png,image/webp" :help="__('students.photo_hint')" />
                @if ($member->photo_path)
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <x-avatar :src="route('staff.photo', $member)" size="48" />
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo" @checked(old('remove_photo'))>
                            <label class="form-check-label" for="remove_photo">{{ __('students.remove_photo') }}</label>
                        </div>
                    </div>
                @endif
            </div>
            <x-form.textarea class="col-md-6 mb-3" name="notes" :label="__('staff.notes')" :value="$member->notes" rows="2" maxlength="2000" :placeholder="__('staff.ph.notes')" />
        </div>
    </div>
</div>
