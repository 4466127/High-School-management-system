{{-- خانه‌های فرم گروه کورس ($group: null = گروه تازه؛ $teacherOptions از صفحه‌ی کورس) --}}
<div class="row">
    <x-form.input class="col-md-6 mb-3" name="name" :label="__('courses.group_name')" :value="$group?->name" :placeholder="__('courses.group_name_ph')" required maxlength="100" :use-old="$useOld" />
    <x-form.select class="col-md-6 mb-3" name="shift" :label="__('courses.shift')" :options="\App\Enums\Shift::options()" :value="$group?->shift" required />
    <x-form.select class="col-12 mb-3" name="staff_id" :label="__('courses.teacher')" :options="$teacherOptions" :value="$group?->staff_id" :placeholder="__('courses.no_teacher')" :help="__('courses.teacher_hint')" />
    <x-form.input class="col-12 mb-3" name="schedule" :label="__('courses.schedule')" :value="$group?->schedule" :placeholder="__('courses.schedule_ph')" maxlength="150" :use-old="$useOld" />
    @if ($group)
        <div class="col-12">
            <input type="hidden" name="is_active" value="0">
            <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($group->is_active)> {{ __('courses.group_active') }}</label>
        </div>
    @endif
</div>
