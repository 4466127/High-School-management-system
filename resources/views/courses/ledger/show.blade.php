@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('courses.course_ledger', ['name' => $course->name])])

@php
    $query = array_filter($filters);
    $open = old('_modal') === 'entryCreate';
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('courses.course_ledger', ['name' => $course->name]) }}</h1>
        <div class="text-center small mb-3">{{ $course->field }} · {{ $course->season->label() }} · @jdate($course->start_date)@if ($course->end_date) ← @jdate($course->end_date)@endif</div>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ __('courses.course_ledger', ['name' => $course->name]) }}</h1>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#entryCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('courses.entry_create') }}</button>
                <a href="{{ route('courses.ledger.show', [$course] + $query + ['print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                <a href="{{ route('courses.ledger.export', [$course] + $query) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> {{ __('attendance.export_csv') }}</a>
                <a href="{{ route('courses.list.show', $course) }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('courses.ledger_intro') }}</div>
    @endif

    @include('courses.ledger._summary', ['summary' => $summary, 'teacherNames' => $teacherNames])

    @unless ($print)
        <form method="GET" class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-5 col-lg-3">
                    <label for="e-type" class="form-label">{{ __('courses.entry_type') }}</label>
                    <select name="type" id="e-type" class="form-select">
                        <option value="">{{ __('app.all') }}</option>
                        @foreach (\App\Enums\CourseEntryType::options() as $v => $l)<option value="{{ $v }}" @selected($filters['type'] === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="voided" value="1" id="e-voided" @checked($filters['voided'])><label class="form-check-label small" for="e-voided">{{ __('finance.show_voided') }}</label></div>
                </div>
                <div class="col-sm-3 col-lg-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('app.filter') }}</button></div>
            </div>
        </form>
    @endunless

    <div @class(['card' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-hover' => ! $print, 'table-bordered table-sm' => $print])>
                <thead>
                <tr>
                    <th>{{ __('finance.date') }}</th>
                    <th>{{ __('courses.entry_type') }}</th>
                    <th>{{ __('courses.channel') }}</th>
                    <th class="text-end">{{ __('finance.amount') }}</th>
                    <th>{{ __('courses.donor') }} / {{ __('courses.teacher') }}</th>
                    <th>{{ __('finance.description') }}</th>
                    @unless ($print)<th></th>@endunless
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr @class(['text-secondary text-decoration-line-through' => $e->isVoided()])>
                        <td class="small text-nowrap">@jdate($e->date)</td>
                        <td><span class="badge {{ $e->entry_type->badgeClass() }}">{{ $e->entry_type->label() }}</span></td>
                        <td class="small">{{ $e->channel->label() }}</td>
                        <td class="text-end fw-medium">{{ \App\Support\Money::format($e->amount) }}</td>
                        <td class="small">
                            @if ($e->entry_type->isIncome()){{ $e->donor_name ?? __('courses.anonymous') }}@endif
                            @if ($e->teacher)<div>{{ __('courses.teacher') }}: {{ $e->teacher->full_name }}</div>@endif
                        </td>
                        <td class="small">{{ $e->description }}@if ($e->isVoided())<div class="text-danger">{{ __('finance.voided') }}: {{ $e->void_reason }}</div>@endif</td>
                        @unless ($print)
                            <td class="text-end">
                                @unless ($e->isVoided())
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#entryVoid{{ $e->id }}">{{ __('finance.void') }}</button>
                                @endunless
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">{{ __('courses.no_entries') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($print)
        <div class="signatures">
            <div>{{ __('app.signature_principal') }}</div>
            <div>{{ __('courses.teacher') }}</div>
        </div>
    @else
        <x-modal-form id="entryCreate" :title="__('courses.entry_create')" :action="route('courses.ledger.store', $course)" :submit="__('app.save')">
            <div class="row">
                <x-form.select class="col-md-6 mb-3" name="entry_type" :label="__('courses.entry_type')" :options="\App\Enums\CourseEntryType::options()" value="donation" required />
                <x-form.select class="col-md-6 mb-3" name="channel" :label="__('courses.channel')" :options="\App\Enums\CourseChannel::options()" value="cashbox" required :help="__('courses.channel_hint')" />
                <x-form.input class="col-md-6 mb-3" name="amount" :label="__('finance.amount').' ('.__('finance.afn').')'" required inputmode="decimal" ltr :use-old="$open" />
                <x-jalali-date class="col-md-6 mb-3" name="date" :label="__('finance.date')" :value="now()" required :use-old="$open" />
                <x-form.input class="col-md-6 mb-3" name="donor_name" :label="__('courses.donor')" :placeholder="__('courses.donor_ph')" maxlength="150" :use-old="$open" />
                <x-form.select class="col-md-6 mb-3" name="staff_id" :label="__('courses.teacher_for_entry')" :options="$teachers->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" placeholder="—" />
                <x-form.input class="col-12 mb-0" name="description" :label="__('finance.description')" maxlength="500" :use-old="$open" />
            </div>
        </x-modal-form>
        @foreach ($entries as $e)
            @unless ($e->isVoided())
                <x-modal-form id="entryVoid{{ $e->id }}" :title="__('finance.void')" :action="route('courses.ledger.void', $e)" method="PATCH" :submit="__('finance.void')" submit-class="btn-danger">
                    <p class="small">{{ $e->entry_type->label() }} — {{ $e->channel->label() }} — {{ \App\Support\Money::format($e->amount) }}</p>
                    <x-form.input name="reason" :label="__('finance.void_reason')" required maxlength="500" :use-old="old('_modal') === 'entryVoid'.$e->id" />
                </x-modal-form>
            @endunless
        @endforeach
    @endif
@endsection
