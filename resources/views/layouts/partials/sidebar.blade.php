<aside class="side" id="side-menu">
    <div class="brand">
        <span class="brand-logo" aria-hidden="true">
            @if (\App\Models\Setting::get('logo_path'))
                <img src="{{ route('school.logo') }}" alt="">
            @else
                <i class="fa-solid fa-graduation-cap"></i>
            @endif
        </span>
        <span class="brand-text">
            {{ \App\Models\Setting::get('school_name') }}
            <small>{{ __('app.name') }}</small>
        </span>
    </div>

    {{-- گروه‌های دارای زیرمنو بازشونده‌اند (فقط یکی در یک زمان)؛ گروه صفحه‌ی فعلی باز می‌ماند --}}
    <ul class="nav-side" id="nav-side">
        @foreach ($menu as $item)
            <li @class(['nav-group-item' => $item['children'] !== [], 'is-open' => $item['children'] !== [] && $item['active']])>
                @if ($item['url'])
                    <a href="{{ $item['url'] }}" @class(['active' => $item['active']])>
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}
                    </a>
                @elseif ($item['children'] === [])
                    <span class="soon"><i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }} <em>{{ __('app.soon') }}</em></span>
                @else
                    <button type="button" @class(['nav-group', 'collapsed' => ! $item['active'], 'has-active' => $item['active']])
                            data-bs-toggle="collapse" data-bs-target="#nav-{{ $item['key'] }}"
                            aria-expanded="{{ $item['active'] ? 'true' : 'false' }}" aria-controls="nav-{{ $item['key'] }}">
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span class="nav-group-label">{{ $item['label'] }}</span>
                        <i class="fa-solid fa-chevron-down nav-caret" aria-hidden="true"></i>
                    </button>
                @endif

                @if ($item['children'] !== [])
                    <ul @class(['nav-sub', 'collapse', 'show' => $item['active']]) id="nav-{{ $item['key'] }}" data-bs-parent="#nav-side">
                        @foreach ($item['children'] as $child)
                            <li>
                                @if ($child['url'])
                                    <a href="{{ $child['url'] }}" @class(['active' => $child['active']])>
                                        <i class="fa-solid {{ $child['icon'] }}" aria-hidden="true"></i> {{ $child['label'] }}
                                    </a>
                                @else
                                    <span class="soon"><i class="fa-solid {{ $child['icon'] }}" aria-hidden="true"></i> {{ $child['label'] }} <em>{{ __('app.soon') }}</em></span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="side-foot">@jdate(now(), 'full') <span class="d-block opacity-75" dir="ltr">v{{ config('app.version') }}</span></div>
</aside>
