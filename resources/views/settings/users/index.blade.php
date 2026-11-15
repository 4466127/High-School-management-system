@extends('layouts.app', ['title' => __('users.title')])

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('users.title') }}</h1>
        <a href="{{ route('settings.users.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ __('users.create') }}
        </a>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label">{{ __('app.search') }}</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('users.search_placeholder') }}">
            </div>
            <div class="col-md-3">
                <label for="role" class="form-label">{{ __('users.roles') }}</label>
                <select id="role" name="role" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->key }}" @selected(request('role') === $role->key)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">{{ __('users.status') }}</label>
                <select id="status" name="status" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="active" @selected(request('status') === 'active')>{{ __('users.active') }}</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('users.inactive') }}</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> {{ __('app.filter') }}</button>
                <a href="{{ route('settings.users.index') }}" class="btn btn-light">{{ __('app.clear_filter') }}</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('users.name') }}</th>
                    <th>{{ __('users.username') }}</th>
                    <th>{{ __('users.roles') }}</th>
                    <th>{{ __('users.status') }}</th>
                    <th>{{ __('users.last_login') }}</th>
                    <th class="text-end">{{ __('app.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            {{ $u->name }}
                            @if ($u->is(auth()->user()))
                                <span class="badge text-bg-secondary">{{ __('users.you') }}</span>
                            @endif
                        </td>
                        <td dir="ltr" class="text-end"><code>{{ $u->username }}</code></td>
                        <td>
                            @foreach ($u->roles as $role)
                                <span class="badge text-bg-light border">{{ $role->label() }}</span>
                            @endforeach
                        </td>
                        <td>
                            @if ($u->is_active)
                                <span class="badge text-bg-success">{{ __('users.active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('users.inactive') }}</span>
                            @endif
                            @if ($u->must_change_password)
                                <span class="badge text-bg-warning">{{ __('users.must_change') }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $u->last_login_at ? \App\Support\JalaliDate::format($u->last_login_at) : __('users.never') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('settings.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i> {{ __('app.edit') }}
                            </a>
                            @can('toggleActive', $u)
                                <form method="POST" action="{{ route('settings.users.toggle-active', $u) }}" class="d-inline"
                                      data-confirm="{{ $u->is_active ? __('users.confirm_deactivate') : __('users.confirm_activate') }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        {{ $u->is_active ? __('users.deactivate') : __('users.activate') }}
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('app.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
