@extends($print ? 'layouts.print' : 'layouts.app', ['title' => $announcement->title])

@section('content')
    @if ($print)
        <div class="announcement-print">
            <h1 class="h4 fw-bold text-center my-3">{{ $announcement->title }}</h1>
            <div class="letter-body">{{ $announcement->body }}</div>
            <div class="small text-secondary mt-4">
                {{ __('announcements.audience') }}: {{ $announcement->audience->label() }}
                @if ($announcement->published_at) · @jdate($announcement->published_at, 'long')@endif
            </div>
        </div>
    @else
        <div class="page-head">
            <h1 class="page-title mb-0">{{ $announcement->title }}</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('announcements.show', [$announcement, 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-print" aria-hidden="true"></i> {{ __('attendance.print') }}</a>
                @can('announcements.manage')
                    <a href="{{ route('announcements.edit', $announcement) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}</a>
                    @foreach (['published' => ['publish', 'btn-success'], 'draft' => ['unpublish', 'btn-outline-secondary'], 'archived' => ['archive', 'btn-outline-secondary']] as $to => [$label, $class])
                        @if ($announcement->status->value !== $to)
                            <form method="POST" action="{{ route('announcements.status', $announcement) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $to }}">
                                <button type="submit" class="btn {{ $class }}">{{ __('announcements.'.$label) }}</button>
                            </form>
                        @endif
                    @endforeach
                @endcan
                <a href="{{ route('announcements.index') }}" class="btn btn-light">{{ __('app.back') }}</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if ($announcement->is_pinned)<span class="badge text-bg-danger">{{ __('announcements.pinned_badge') }}</span>@endif
                    <span class="badge text-bg-light border">{{ __('announcements.audience') }}: {{ $announcement->audience->label() }}</span>
                    <span class="badge {{ $announcement->status->badgeClass() }}">{{ $announcement->status->label() }}</span>
                    @if ($announcement->published_at)<span class="small text-secondary">{{ __('announcements.published_on') }}: @jdate($announcement->published_at, 'long')</span>@endif
                    @if ($announcement->expires_on)<span class="small text-secondary">· {{ __('announcements.expires_on') }}: @jdate($announcement->expires_on, 'long')</span>@endif
                </div>
                <div class="letter-body">{{ $announcement->body }}</div>
                @if ($announcement->attachment_path)
                    <a href="{{ route('announcements.attachment', $announcement) }}" class="btn btn-sm btn-outline-secondary mt-3"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> {{ $announcement->attachment_name }}</a>
                @endif
            </div>
        </div>
    @endif
@endsection
