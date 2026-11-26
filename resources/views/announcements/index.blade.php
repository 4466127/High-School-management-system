@extends('layouts.app', ['title' => __('announcements.title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('announcements.title') }}</h1>
        @if ($manager)
            <a href="{{ route('announcements.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('announcements.create') }}</a>
        @endif
    </div>
    <div class="alert alert-light border small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('announcements.hint') }}</div>

    @if ($manager)
        <ul class="nav nav-pills mb-3">
            <li class="nav-item"><a @class(['nav-link', 'active' => ! $status]) href="{{ route('announcements.index') }}">{{ __('announcements.all_active') }}</a></li>
            @foreach (\App\Enums\AnnouncementStatus::cases() as $case)
                <li class="nav-item"><a @class(['nav-link', 'active' => $status === $case->value]) href="{{ route('announcements.index', ['status' => $case->value]) }}">{{ $case->label() }}</a></li>
            @endforeach
        </ul>
    @endif

    <div class="d-flex flex-column gap-2">
        @forelse ($announcements as $a)
            <a href="{{ route('announcements.show', $a) }}" class="card card-body text-decoration-none text-body announcement-item">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    @if ($a->is_pinned)<span class="badge text-bg-danger"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> {{ __('announcements.pinned_badge') }}</span>@endif
                    <span class="fw-bold flex-grow-1">{{ $a->title }}</span>
                    <span class="badge text-bg-light border">{{ $a->audience->label() }}</span>
                    @if ($manager)<span class="badge {{ $a->status->badgeClass() }}">{{ $a->status->label() }}</span>@endif
                    @if ($a->expires_on && ! $a->isActive() && $a->status === \App\Enums\AnnouncementStatus::Published)<span class="badge text-bg-secondary">{{ __('announcements.expired') }}</span>@endif
                </div>
                <div class="small text-secondary text-truncate">{{ \Illuminate\Support\Str::limit($a->body, 180) }}</div>
                <div class="small text-secondary mt-1">
                    @if ($a->published_at)@jdate($a->published_at, 'long')@endif
                    @if ($a->author) · {{ $a->author->name }}@endif
                    @if ($a->attachment_path) · <i class="fa-solid fa-paperclip" aria-hidden="true"></i>@endif
                </div>
            </a>
        @empty
            <div class="card card-body text-center text-secondary py-4">{{ __('announcements.none') }}</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $announcements->links() }}</div>
@endsection
