@extends($print ? 'layouts.print' : 'layouts.app', ['title' => __('teaching.plan_title')])

@php
    $editable = $plan->status->editable();
    $statuses = \App\Enums\PlanItemStatus::options();
    $n = fn ($v) => \App\Support\Digits::toPersian((string) $v);
@endphp

@section('content')
    @if ($print)
        <h1 class="h5 fw-bold text-center mb-1">{{ __('teaching.plan_title') }} — {{ $plan->subject->name }}</h1>
        <p class="text-center small mb-3">{{ $plan->section->label }} · {{ __('timetable.teacher') }}: {{ $plan->teacher?->full_name ?? '—' }} · {{ __('teaching.status') }}: {{ $plan->status->label() }}</p>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">
                {{ $plan->subject->name }} <span class="text-secondary fs-6">— {{ $plan->section->label }}</span>
                <span class="badge {{ $plan->status->badgeClass() }} fs-6 align-middle">{{ $plan->status->label() }}</span>
            </h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('teaching.plans.show', [$plan, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                @if ($editable && $plan->items->isNotEmpty())
                    <form method="POST" action="{{ route('teaching.plans.submit', $plan) }}" data-confirm="{{ __('teaching.submit_confirm') }}">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> {{ __('teaching.submit') }}</button>
                    </form>
                @endif
                @if ($canReview && in_array($plan->status, [\App\Enums\PlanStatus::Submitted, \App\Enums\PlanStatus::Approved], true))
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewPlan"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> {{ __('teaching.review') }}</button>
                @endif
                <a href="{{ route('teaching.plans.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>

        <div class="small text-secondary mb-2">
            {{ __('timetable.teacher') }}: {{ $plan->teacher?->full_name ?? '—' }}
            @if ($plan->submitted_at) · {{ __('teaching.submitted_at') }}: @jdate($plan->submitted_at)@endif
            @if ($plan->reviewed_at) · {{ __('teaching.reviewed_by', ['name' => $plan->reviewer?->name ?? '—']) }} @jdate($plan->reviewed_at)@endif
        </div>
        @if ($plan->review_note)
            <div @class(['alert py-2', 'alert-warning' => $plan->status === \App\Enums\PlanStatus::Returned, 'alert-success' => $plan->status !== \App\Enums\PlanStatus::Returned])>
                <i class="fa-solid fa-comment" aria-hidden="true"></i> {{ __('teaching.review_note') }}: {{ $plan->review_note }}
            </div>
        @endif
        @unless ($editable)
            <div class="alert alert-light border small">{{ __('teaching.locked_hint') }}</div>
        @endunless
    @endif

    {{-- پیشرفت --}}
    <div class="row g-2 mb-3">
        @foreach (['percent' => 'fa-chart-pie', 'total' => 'fa-list-ol', 'done' => 'fa-circle-check', 'overdue' => 'fa-triangle-exclamation'] as $key => $icon)
            <div class="col-6 col-md-3">
                <div class="card card-body py-2 text-center">
                    <div class="small text-secondary"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ __('teaching.stat.'.$key) }}</div>
                    <div @class(['fs-5 fw-bold', 'text-danger' => $key === 'overdue' && $progress['overdue'] > 0])>{{ $n($progress[$key]) }}{{ $key === 'percent' ? '٪' : '' }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($plan->notes || ($editable && ! $print))
        @if ($editable && ! $print)
            <form method="POST" action="{{ route('teaching.plans.notes', $plan) }}" class="mb-3">
                @csrf
                @method('PUT')
                <x-form.textarea name="notes" :label="__('teaching.notes')" :value="$plan->notes" rows="2" maxlength="1000" :placeholder="__('teaching.notes_ph')" class="mb-1" />
                <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('app.save') }}</button>
            </form>
        @else
            <p class="small"><span class="fw-medium">{{ __('teaching.notes') }}:</span> <span class="letter-body">{{ $plan->notes }}</span></p>
        @endif
    @endif

    {{-- موضوع‌ها --}}
    <div @class(['card mb-3' => ! $print])>
        <div class="table-responsive">
            <table @class(['table align-middle mb-0', 'table-bordered table-sm' => $print])>
                <thead><tr>
                    <th style="width: 50px">{{ __('teaching.week') }}</th><th>{{ __('teaching.dates') }}</th><th>{{ __('teaching.topic') }}</th>
                    <th style="width: 60px">{{ __('teaching.hours') }}</th><th>{{ __('teaching.progress') }}</th>@unless ($print)<th></th>@endunless
                </tr></thead>
                <tbody>
                @forelse ($plan->items as $item)
                    <tr @class(['table-warning' => $item->isOverdue()])>
                        <td>{{ $item->week_no ? $n($item->week_no) : '' }}</td>
                        <td class="small text-nowrap">@if ($item->planned_from)@jdate($item->planned_from)@endif @if ($item->planned_to)— @jdate($item->planned_to)@endif</td>
                        <td>
                            <div class="fw-medium">{{ $item->topic }}</div>
                            @if ($item->objectives)<div class="small text-secondary">{{ $item->objectives }}</div>@endif
                        </td>
                        <td>{{ $item->planned_hours ? $n($item->planned_hours) : '' }}</td>
                        <td class="small">
                            <span class="badge {{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                            @if ($item->completed_on) @jdate($item->completed_on)@endif
                            @if ($item->isOverdue()) <span class="text-danger">{{ __('teaching.overdue') }}</span>@endif
                            @if ($item->note)<div class="text-secondary">{{ $item->note }}</div>@endif
                        </td>
                        @unless ($print)
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#prog{{ $item->id }}"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('teaching.mark_progress') }}</button>
                                @if ($editable)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#item{{ $item->id }}" title="{{ __('app.edit') }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                    <form method="POST" action="{{ route('teaching.items.destroy', $item) }}" class="d-inline" data-confirm="{{ __('teaching.remove_confirm') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('teaching.remove') }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                @endif
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('teaching.no_topics_yet') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($print)
        <div class="signatures">
            <div>{{ __('teaching.sign_teacher') }}</div>
            <div>{{ __('teaching.sign_principal') }}</div>
        </div>
    @endif

    {{-- افزودن موضوع‌ها و کاپی --}}
    @if ($editable && ! $print)
        <div class="row g-3">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('teaching.plans.topics', $plan) }}" class="card form-section" novalidate>
                    @csrf
                    <div class="card-header"><i class="fa-solid fa-list" aria-hidden="true"></i> {{ __('teaching.add_topics') }}</div>
                    <div class="card-body">
                        <x-form.textarea name="topics" :label="__('teaching.topics')" rows="6" required :placeholder="__('teaching.topics_ph')" :help="__('teaching.topics_hint')" />
                        <div class="row">
                            <x-jalali-date class="col-sm-6 mb-3" name="start_date" :label="__('teaching.start_date')" :help="__('teaching.start_date_hint')" />
                            <x-form.input class="col-sm-6 mb-3" name="hours" type="number" min="0" max="40" :label="__('teaching.hours_each')" :placeholder="__('teaching.hours_ph')" />
                        </div>
                    </div>
                    <div class="card-footer bg-transparent"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('teaching.add_topics') }}</button></div>
                </form>
            </div>
            @if ($plan->items->isEmpty() && $siblings->isNotEmpty())
                <div class="col-lg-5">
                    <form method="POST" action="{{ route('teaching.plans.copy', $plan) }}" class="card card-body">
                        @csrf
                        <label for="tp-copy" class="form-label fw-medium">{{ __('teaching.copy_from') }}</label>
                        <select name="from" id="tp-copy" class="form-select mb-2">
                            @foreach ($siblings as $s)<option value="{{ $s->id }}">{{ $s->section->label }} ({{ __('teaching.topics_n', ['count' => $n($s->items_count)]) }})</option>@endforeach
                        </select>
                        <div class="form-text mt-0 mb-2">{{ __('teaching.copy_hint') }}</div>
                        <button type="submit" class="btn btn-outline-primary">{{ __('teaching.copy') }}</button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    {{-- پنجره‌ها --}}
    @unless ($print)
        @foreach ($plan->items as $item)
            @php($open = old('_modal') === 'prog'.$item->id)
            <x-modal-form :id="'prog'.$item->id" :title="__('teaching.mark_progress').' — '.$item->topic" :action="route('teaching.items.progress', $item)" :submit="__('app.save')">
                <x-form.select :id="'ps'.$item->id" name="status" :label="__('teaching.progress')" :options="$statuses" :value="$open ? old('status') : ($item->status === \App\Enums\PlanItemStatus::Planned ? 'done' : $item->status->value)" required />
                <x-jalali-date :id="'pc'.$item->id" name="completed_on" :label="__('teaching.completed_on')" :value="$item->completed_on ?? now()" :use-old="$open" :help="__('teaching.completed_on_hint')" />
                <x-form.input :id="'pn'.$item->id" name="note" :label="__('teaching.item_note')" :value="$item->note" maxlength="300" :use-old="$open" :placeholder="__('teaching.item_note_ph')" />
            </x-modal-form>
            @if ($editable)
                @php($open = old('_modal') === 'item'.$item->id)
                <x-modal-form :id="'item'.$item->id" :title="__('app.edit').' — '.$item->topic" :action="route('teaching.items.update', $item)" method="PUT" :submit="__('app.save')">
                    <x-form.input :id="'it'.$item->id" name="topic" :label="__('teaching.topic')" :value="$item->topic" required maxlength="200" :use-old="$open" />
                    <x-form.textarea :id="'io'.$item->id" name="objectives" :label="__('teaching.objectives')" :value="$item->objectives" rows="2" maxlength="1000" :use-old="$open" :placeholder="__('teaching.objectives_ph')" />
                    <div class="row">
                        <x-form.input class="col-6 mb-3" :id="'iw'.$item->id" name="week_no" type="number" min="1" max="60" :label="__('teaching.week')" :value="$item->week_no" :use-old="$open" />
                        <x-form.input class="col-6 mb-3" :id="'ih'.$item->id" name="planned_hours" type="number" min="0" max="40" :label="__('teaching.hours')" :value="$item->planned_hours" :use-old="$open" />
                        <x-jalali-date class="col-6 mb-3" :id="'if'.$item->id" name="planned_from" :label="__('teaching.from')" :value="$item->planned_from" :use-old="$open" />
                        <x-jalali-date class="col-6 mb-3" :id="'iu'.$item->id" name="planned_to" :label="__('teaching.to')" :value="$item->planned_to" :use-old="$open" />
                    </div>
                </x-modal-form>
            @endif
        @endforeach

        @if ($canReview)
            @php($open = old('_modal') === 'reviewPlan')
            <x-modal-form id="reviewPlan" :title="__('teaching.review')" :action="route('teaching.plans.review', $plan)" :submit="__('app.save')">
                <div class="mb-3">
                    @foreach (['approve', 'return'] as $d)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision" value="{{ $d }}" id="dec-{{ $d }}" @checked(($open ? old('decision') : 'approve') === $d)>
                            <label class="form-check-label" for="dec-{{ $d }}">{{ __('teaching.decision_'.$d) }}</label>
                        </div>
                    @endforeach
                </div>
                <x-form.textarea id="review-note" name="note" :label="__('teaching.review_note')" rows="2" maxlength="1000" :use-old="$open" :placeholder="__('teaching.review_note_ph')" :help="__('teaching.review_note_hint')" />
            </x-modal-form>
        @endif
    @endunless
@endsection
