{{-- نتیجه‌ی سال‌هایی که در سیستم نیست (مرحله‌ی ۱۴؛ فقط students.manage) --}}
@php($fmt = fn ($n) => $n === null ? '—' : \App\Support\Digits::toPersian(\App\Services\MarkService::format($n)))
<div class="d-flex align-items-center gap-2 mt-3 mb-2">
    <h2 class="h6 fw-bold mb-0 flex-grow-1">{{ __('past_results.list_title') }}</h2>
    @unless ($student->trashed())
        <a href="{{ route('student-past-results.create', $student) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('past_results.add') }}</a>
    @endunless
</div>
@if ($pastResults->isEmpty())
    <div class="card card-body small text-secondary">{{ __('past_results.none') }}</div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>{{ __('academics.year') }}</th><th>{{ __('academics.grade') }}</th><th>{{ __('past_results.school_name') }}</th>
                    <th>{{ __('exams.average') }}</th><th>{{ __('exams.result') }}</th><th>{{ __('past_results.subjects') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($pastResults as $r)
                    <tr>
                        <td>@fa($r->year_name)</td>
                        <td>{{ $r->grade_name }}</td>
                        <td class="small">{{ $r->school_name ?: __('past_results.this_school') }}</td>
                        <td>{{ $fmt($r->average) }}</td>
                        <td>@if ($r->result)<span class="badge {{ $r->result->badgeClass() }}">{{ $r->result->label() }}</span>@else — @endif
                            @if ($r->grade_letter)<span class="small text-secondary">· {{ __('exams.grades.'.$r->grade_letter) }}</span>@endif</td>
                        <td class="small">@fa(count($r->subjects ?? []))</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('student-past-results.edit', $r) }}" class="btn btn-sm btn-outline-primary">{{ __('app.edit') }}</a>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#prCancel{{ $r->id }}">{{ __('past_results.cancel') }}</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @foreach ($pastResults as $r)
        <x-modal-form :id="'prCancel'.$r->id" :title="__('past_results.cancel').' — '.$r->grade_name.' '.\App\Support\Digits::toPersian($r->year_name)" :action="route('student-past-results.cancel', $r)" :submit="__('past_results.cancel')">
            <p class="small text-secondary">{{ __('past_results.cancel_hint') }}</p>
            <x-form.input :id="'prr'.$r->id" name="reason" :label="__('students.reason')" required maxlength="500" :use-old="old('_modal') === 'prCancel'.$r->id" :placeholder="__('past_results.cancel_reason_ph')" />
        </x-modal-form>
    @endforeach
@endif
