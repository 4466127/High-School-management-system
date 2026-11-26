@extends('layouts.app', ['title' => __('import.title')])

@php
    // ستون‌هایی که در پیش‌نمایش نشان داده می‌شوند (همه‌ی ستون‌های فایل در جدول جا نمی‌شوند)
    $shown = match ($type) {
        'students' => ['admission_no', 'first_name', 'last_name', 'father_name', 'gender', 'birth_date', 'phone', 'section_label', 'guardian_name'],
        'graduates' => ['admission_no', 'first_name', 'last_name', 'father_name', 'gender', 'status_label', 'graduation_year', 'certificate_no', 'avg_10', 'avg_11', 'avg_12'],
        default => ['staff_type', 'first_name', 'last_name', 'father_name', 'gender', 'phone', 'education', 'position'],
    };
    $labels = collect($columns)->map(fn ($c) => $c[0])->all() + ['section_label' => __('academics.section'), 'status_label' => __('students.status')];
    $badges = ['ok' => 'text-bg-success', 'error' => 'text-bg-danger', 'duplicate' => 'text-bg-warning'];
@endphp

@section('content')
    <div class="page-head">
        <h1 class="page-title mb-0">{{ __('import.preview_title', ['type' => __('import.type_'.$type)]) }}</h1>
        <a href="{{ route('import.index', ['type' => $type]) }}" class="btn btn-light">{{ __('import.another_file') }}</a>
    </div>

    @if ($missing)
        <div class="alert alert-danger">{{ __('import.missing_columns', ['columns' => implode('، ', $missing)]) }}</div>
    @else
        @if ($unknown)
            <div class="alert alert-warning small">{{ __('import.unknown_columns', ['columns' => implode('، ', $unknown)]) }}</div>
        @endif

        <div class="card card-body mb-3 d-flex flex-row flex-wrap align-items-center gap-2">
            <span class="fw-bold">{{ __('import.summary', [
                'ok' => \App\Support\Digits::toPersian((string) $counts['ok']),
                'error' => \App\Support\Digits::toPersian((string) $counts['error']),
                'duplicate' => \App\Support\Digits::toPersian((string) $counts['duplicate']),
            ]) }}</span>
            @if ($counts['ok'] > 0)
                <form method="POST" action="{{ route('import.store') }}" class="ms-auto" data-confirm="{{ __('import.confirm_question') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ __('import.confirm', ['count' => \App\Support\Digits::toPersian((string) $counts['ok'])]) }}</button>
                </form>
            @else
                <span class="text-danger ms-auto">{{ __('import.nothing_to_import') }}</span>
            @endif
        </div>
        @if ($counts['error'] > 0)
            <div class="small text-secondary mb-2">{{ __('import.fix_and_retry') }}</div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('import.line') }}</th>
                        <th>{{ __('import.status') }}</th>
                        @foreach ($shown as $key)<th>{{ $labels[$key] ?? $key }}</th>@endforeach
                        <th>{{ __('import.problems') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['table-danger' => $row['status'] === 'error', 'table-warning' => $row['status'] === 'duplicate'])>
                            <td>@fa($row['line'])</td>
                            <td><span class="badge {{ $badges[$row['status']] }}">{{ __('import.status_'.$row['status']) }}</span></td>
                            @foreach ($shown as $key)<td class="small">{{ $row['values'][$key] ?? '' }}</td>@endforeach
                            <td class="small">
                                @foreach ($row['errors'] as $error)<div>{{ $error }}</div>@endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
