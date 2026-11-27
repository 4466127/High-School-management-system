@extends('layouts.app', ['title' => __('activity.title')])

@section('content')
    <h1 class="page-title">{{ __('activity.title') }}</h1>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="user" class="form-label">{{ __('activity.user') }}</label>
                <select id="user" name="user" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="action" class="form-label">{{ __('activity.action') }}</label>
                <select id="action" name="action" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($actionGroups as $group)
                        <option value="{{ $group }}." @selected(request('action') === $group.'.')>{{ __('activity.groups.'.$group) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label">{{ __('app.from_date') }}</label>
                <input type="text" id="from" name="from" value="{{ request('from') }}" data-jdate autocomplete="off" dir="ltr" class="form-control" placeholder="{{ __('app.date_placeholder_short') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label">{{ __('app.to_date') }}</label>
                <input type="text" id="to" name="to" value="{{ request('to') }}" data-jdate autocomplete="off" dir="ltr" class="form-control" placeholder="{{ __('app.date_placeholder_short') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill">{{ __('app.filter') }}</button>
                <a href="{{ route('settings.activity-logs.index') }}" class="btn btn-light" title="{{ __('app.clear_filter') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('activity.time') }}</th>
                    <th>{{ __('activity.user') }}</th>
                    <th>{{ __('activity.action') }}</th>
                    <th>{{ __('activity.description') }}</th>
                    <th>{{ __('activity.changes') }}</th>
                    <th>{{ __('activity.ip') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">@jdate($log->created_at) <span>{{ \App\Support\Clock::withPeriod($log->created_at) }}</span></td>
                        <td>{{ $log->user?->name ?? __('activity.system') }}</td>
                        <td class="text-nowrap">{{ $log->actionLabel() }}</td>
                        <td class="small">{{ $log->description }}</td>
                        <td>
                            @if ($log->old_values || $log->new_values)
                                <dl class="log-values">
                                    @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                        @php
                                            $show = fn ($v) => is_array($v) ? implode('، ', $v) : (is_bool($v) ? ($v ? __('app.yes') : __('app.no')) : (string) $v);
                                        @endphp
                                        <dt>{{ __('activity.fields.'.$field) !== 'activity.fields.'.$field ? __('activity.fields.'.$field) : $field }}:</dt>
                                        <dd>
                                            @if (isset($log->old_values[$field]))
                                                <del class="text-danger">{{ $show($log->old_values[$field]) }}</del>
                                            @endif
                                            @if (array_key_exists($field, $log->new_values ?? []))
                                                <span class="text-success">{{ $show($log->new_values[$field]) }}</span>
                                            @endif
                                        </dd>
                                    @endforeach
                                </dl>
                            @endif
                        </td>
                        <td dir="ltr" class="small text-end">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('app.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
