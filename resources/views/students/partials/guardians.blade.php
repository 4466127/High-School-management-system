{{-- زبانه‌ی سرپرستان (فقط با students.manage). $student با guardians بارگذاری شده --}}
@php($canEdit = auth()->user()->can('update', $student))

<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 fw-bold mb-0">{{ __('students.guardians') }}</h2>
    @if ($canEdit)
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#guardianAdd">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('students.guardian_add') }}
        </button>
    @endif
</div>

@forelse ($student->guardians as $guardian)
    @php($sharedCount = $guardian->students()->count())
    <div class="card mb-2">
        <div class="card-body d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-bold">
                    {{ $guardian->name }}
                    <span class="text-secondary fw-normal">({{ $guardian->pivot->relation }})</span>
                    @if ($guardian->pivot->is_primary)
                        <span class="badge text-bg-primary">{{ __('students.guardian_primary') }}</span>
                    @endif
                </div>
                <div class="small text-secondary">
                    @if ($guardian->phone)<span dir="ltr">{{ $guardian->phone }}</span>@endif
                    @if ($guardian->phone2) · <span dir="ltr">{{ $guardian->phone2 }}</span>@endif
                    @if ($guardian->occupation) · {{ $guardian->occupation }}@endif
                </div>
                @if ($guardian->address)<div class="small text-secondary">{{ $guardian->address }}</div>@endif
                @if ($sharedCount > 1)
                    <div class="small text-info-emphasis">{{ __('students.guardian_shared', ['count' => \App\Support\Digits::toPersian($sharedCount)]) }}</div>
                @endif
            </div>
            @if ($canEdit)
                <div class="d-flex gap-2 align-items-start">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#guardianEdit{{ $guardian->id }}">{{ __('app.edit') }}</button>
                    <form method="POST" action="{{ route('students.guardians.destroy', [$student, $guardian]) }}" data-confirm="{{ __('students.confirm_guardian_remove') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('students.guardian_remove') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @if ($canEdit)
        @php($modalId = 'guardianEdit'.$guardian->id)
        @php($own = old('_modal') === $modalId)
        <x-modal-form :id="$modalId" :title="__('students.guardian_edit')" :action="route('students.guardians.update', [$student, $guardian])" method="PUT" :submit="__('app.save')">
            @if ($sharedCount > 1)
                <div class="alert alert-info small py-2">{{ __('students.guardian_shared', ['count' => \App\Support\Digits::toPersian($sharedCount)]) }}</div>
            @endif
            @include('students.partials.guardian-fields', ['g' => $guardian, 'relation' => $guardian->pivot->relation, 'primary' => $guardian->pivot->is_primary, 'own' => $own, 'prefix' => $modalId])
        </x-modal-form>
    @endif
@empty
    <div class="card card-body text-secondary">{{ __('students.no_guardians') }}</div>
@endforelse

@if ($canEdit)
    <x-modal-form id="guardianAdd" :title="__('students.guardian_add')" :action="route('students.guardians.store', $student)" :submit="__('app.add')">
        <p class="form-text mt-0">{{ __('students.guardian_link_hint') }}</p>
        @include('students.partials.guardian-fields', ['g' => null, 'relation' => null, 'primary' => $student->guardians->isEmpty(), 'own' => old('_modal') === 'guardianAdd', 'prefix' => 'guardianAdd'])
    </x-modal-form>
    @include('students.partials.relations-datalist')
@endif
