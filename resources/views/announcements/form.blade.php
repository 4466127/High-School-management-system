@extends('layouts.app', ['title' => $announcement->exists ? __('announcements.edit') : __('announcements.create')])

@section('content')
    <h1 class="page-title">{{ $announcement->exists ? __('announcements.edit') : __('announcements.create') }}</h1>

    <form method="POST" action="{{ $announcement->exists ? route('announcements.update', $announcement) : route('announcements.store') }}" enctype="multipart/form-data" class="card form-section" novalidate>
        @csrf
        @if ($announcement->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row">
                <x-form.input class="col-md-8 mb-3" name="title" :label="__('announcements.title_field')" :value="$announcement->title" required maxlength="200" />
                <x-form.select class="col-md-4 mb-3" name="audience" :label="__('announcements.audience')" :options="\App\Enums\AnnouncementAudience::options()" :value="$announcement->audience?->value" required />
            </div>
            <x-form.textarea name="body" :label="__('announcements.body')" :value="$announcement->body" rows="8" required maxlength="10000" />
            <div class="row">
                <div class="col-md-4 mb-3">
                    <x-jalali-date name="expires_on" :label="__('announcements.expires_on')" :value="$announcement->expires_on" :help="__('announcements.expires_hint')" />
                </div>
                <div class="col-md-8 mb-3">
                    <x-form.input class="mb-1" name="attachment" type="file" :label="__('announcements.attachment')" accept=".pdf,image/jpeg,image/png,image/webp" :help="__('announcements.attachment_hint')" />
                    @if ($announcement->attachment_path)
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="remove_attachment">
                            <label class="form-check-label" for="remove_attachment">{{ __('announcements.remove_attachment') }} ({{ $announcement->attachment_name }})</label>
                        </div>
                    @endif
                </div>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="is_pinned" @checked(old('is_pinned', $announcement->is_pinned))>
                <label class="form-check-label" for="is_pinned">{{ __('announcements.pinned') }}</label>
            </div>
            @unless ($announcement->exists)
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="publish" value="1" id="publish" @checked(old('publish', true))>
                    <label class="form-check-label" for="publish">{{ __('announcements.publish_now') }}</label>
                </div>
            @endunless
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('app.save') }}</button>
            <a href="{{ $announcement->exists ? route('announcements.show', $announcement) : route('announcements.index') }}" class="btn btn-light">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
