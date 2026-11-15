{{-- $term (AcademicTerm|null), $own, $order, $prefix --}}
<div class="row">
    <x-form.input class="col-8 mb-3" :id="$prefix.'_name'" name="name" :label="__('academics.term_name')" :value="$term?->name" required maxlength="100" :placeholder="__('academics.term_name_ph')" :use-old="$own" />
    <x-form.input class="col-4 mb-3" :id="$prefix.'_order'" name="sort_order" type="number" min="1" max="20" :label="__('academics.term_order')" :value="$order" required :use-old="$own" />
    <x-jalali-date class="col-6 mb-3" :id="$prefix.'_start'" name="start_date" :label="__('academics.start_date')" :value="$term?->start_date" :use-old="$own" />
    <x-jalali-date class="col-6 mb-3" :id="$prefix.'_end'" name="end_date" :label="__('academics.end_date')" :value="$term?->end_date" :use-old="$own" />
</div>
