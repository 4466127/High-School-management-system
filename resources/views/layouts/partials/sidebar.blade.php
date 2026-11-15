<aside class="side" id="side-menu">
    <div class="brand">
        {{ \App\Models\Setting::get('school_name') }}
        <small>{{ __('app.name') }}</small>
    </div>

    <ul class="nav-side">
        @foreach ($menu as $item)
            <li>
                @if ($item['url'])
                    <a href="{{ $item['url'] }}" @class(['active' => $item['active']])>
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}
                    </a>
                @elseif ($item['children'] === [])
                    <span class="soon"><i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }} <em>{{ __('app.soon') }}</em></span>
                @else
                    <span class="nav-group"><i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}</span>
                @endif

                @if ($item['children'] !== [])
                    <ul class="nav-sub">
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

    <div class="side-foot">@jdate(now(), 'full')</div>
</aside>
