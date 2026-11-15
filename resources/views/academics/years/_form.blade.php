{{-- $year --}}
<div class="row">
    <x-form.input class="col-md-4 mb-3" name="name" :label="__('academics.year_name')" :value="$year->name" required maxlength="20" :placeholder="__('academics.year_name_ph')" />
    <x-jalali-date class="col-md-4 mb-3" name="start_date" :label="__('academics.start_date')" :value="$year->start_date" required />
    <x-jalali-date class="col-md-4 mb-3" name="end_date" :label="__('academics.end_date')" :value="$year->end_date" required />
</div>
