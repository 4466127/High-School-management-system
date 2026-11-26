{{--
    پنجره‌ی Bootstrap با یک فرم (برای کارهایی که دلیل یا تأیید می‌خواهند).
    <x-modal-form id="deleteModal" :title="..." :action="route(...)" method="DELETE" :submit="..." submit-class="btn-danger">
        ...خانه‌ها (با :use-old="$modalOpen")...
    </x-modal-form>
    دکمه‌ی باز کردن: <button data-bs-toggle="modal" data-bs-target="#deleteModal">
    اگر همین فرم فرستاده شد و خطا داشت، پنجره خودکار دوباره باز می‌شود (school.js، data-open-on-load).
    در خود صفحه: $open = old('_modal') === 'deleteModal'
--}}
@props(['id', 'title', 'action', 'method' => 'POST', 'submit' => null, 'submitClass' => 'btn-primary', 'files' => false, 'size' => null])

@php($open = old('_modal') === $id && $errors->any())

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true" @if ($open) data-open-on-load @endif>
    <div @class(['modal-dialog', 'modal-'.$size => $size])>
        <form method="POST" action="{{ $action }}" class="modal-content" @if ($files) enctype="multipart/form-data" @endif novalidate>
            @csrf
            @unless (strtoupper($method) === 'POST')
                @method($method)
            @endunless
            <input type="hidden" name="_modal" value="{{ $id }}">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $id }}-title">{{ $title }}</h2>
                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn {{ $submitClass }}">{{ $submit ?? __('app.confirm') }}</button>
            </div>
        </form>
    </div>
</div>
