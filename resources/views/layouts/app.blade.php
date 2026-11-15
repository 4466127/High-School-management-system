<!doctype html>
<html lang="fa" dir="rtl">
<head>
    @include('layouts.partials.head')
</head>
<body>
<div class="app">
    @include('layouts.partials.sidebar')

    <main class="main">
        <div class="topbar">
            <span class="topbar-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> @jdate(now(), 'full')</span>
            <div class="topbar-user dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                    {{ auth()->user()->name }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-item-text small text-secondary">
                        {{ auth()->user()->roles->map->label()->join('، ') }}
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('password.edit') }}">
                            <i class="fa-solid fa-key" aria-hidden="true"></i> {{ __('app.change_password') }}
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> {{ __('app.logout') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        @include('layouts.partials.alerts')

        @yield('content')
    </main>
</div>
@include('layouts.partials.scripts')
</body>
</html>
