{{-- $section, $year, $grades, $teachers --}}
<div class="card form-section mb-3">
    <div class="card-header"><i class="fa-solid fa-chalkboard" aria-hidden="true"></i> {{ __('academics.section') }} — {{ __('academics.year') }} @fa($year->name)</div>
    <div class="card-body">
        @unless ($section->exists)
            <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
        @endunless
        <div class="row">
            <x-form.select class="col-md-4 mb-3" name="grade_level_id" :label="__('academics.grade')" :options="$grades->pluck('name', 'id')->all()" :value="$section->grade_level_id" :placeholder="__('students.choose')" required />
            <x-form.input class="col-md-4 mb-3" name="name" :label="__('academics.section_name')" :value="$section->name" required maxlength="20" :placeholder="__('academics.section_name_ph')" />
            <x-form.select class="col-md-4 mb-3" name="shift" :label="__('academics.shift')" :options="\App\Enums\Shift::options()" :value="$section->shift" required />
            <x-form.select class="col-md-4 mb-3" name="class_teacher_id" :label="__('academics.class_teacher')"
                           :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name.($t->specialization ? ' — '.$t->specialization : '')])->all()"
                           :value="$section->class_teacher_id" :placeholder="__('academics.no_class_teacher')" />
            <x-form.input class="col-md-4 mb-3" name="room" :label="__('academics.room')" :value="$section->room" maxlength="50" :placeholder="__('academics.room_ph')" />
            <x-form.input class="col-md-4 mb-3" name="capacity" type="number" min="1" max="500" :label="__('academics.capacity')" :value="$section->capacity" :placeholder="__('academics.capacity_ph')" />
        </div>
    </div>
</div>
