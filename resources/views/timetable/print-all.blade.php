@extends('layouts.print', ['title' => __('timetable.print_all'), 'noHeader' => true])

@section('content')
    @forelse ($items as $item)
        <div class="timetable-page print-landscape">
            @include('layouts.partials.letterhead')
            <h1 class="h5 fw-bold text-center mb-3">{{ __('timetable.title') }} — {{ $item['section']->label }} — {{ __('academics.year') }} @fa($year->name)</h1>
            @if ($item['periods']->isEmpty())
                <p class="text-secondary text-center">{{ __('timetable.no_periods', ['shift' => $item['section']->shift->label()]) }}</p>
            @else
                @include('timetable._grid', ['mode' => 'section', 'editable' => false, 'grid' => $item['grid'], 'periods' => $item['periods']])
            @endif
        </div>
    @empty
        <p class="text-center text-secondary">{{ __('academics.no_sections') }}</p>
    @endforelse
@endsection
