{{-- زبانه‌ی اسناد (فقط با students.manage) --}}
@php($canEdit = auth()->user()->can('update', $student))

@if ($canEdit)
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('students.documents.store', $student) }}" enctype="multipart/form-data" class="row g-2 align-items-end" novalidate>
                @csrf
                <x-form.input class="col-md-4 mb-0" name="title" :label="__('students.document_title')" :placeholder="__('students.document_title_placeholder')" required maxlength="150" />
                <x-form.select class="col-md-2 mb-0" name="category" :label="__('students.document_category')" :options="\App\Enums\DocumentCategory::options()" value="other" />
                <x-form.input class="col-md-4 mb-0" name="file" type="file" :label="__('students.document_file')" accept=".pdf,image/jpeg,image/png,image/webp" required />
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-upload" aria-hidden="true"></i> {{ __('students.document_upload') }}</button>
                </div>
                <div class="col-12 form-text">{{ __('students.document_hint') }}</div>
            </form>
        </div>
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>{{ __('students.document_title') }}</th>
                <th>{{ __('students.document_category') }}</th>
                <th>{{ __('students.document_file') }}</th>
                <th>{{ __('students.document_uploaded_by') }}</th>
                <th class="text-end">{{ __('app.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($student->documents as $document)
                <tr>
                    <td>{{ $document->title }}</td>
                    <td class="small text-nowrap"><i class="fa-solid {{ $document->category->icon() }} text-secondary" aria-hidden="true"></i> {{ $document->category->label() }}</td>
                    <td class="small" dir="ltr">{{ $document->original_name }} <span class="text-secondary">(@fa(number_format($document->size / 1024)) KB)</span></td>
                    <td class="small">{{ $document->uploader?->name }} — @jdate($document->created_at)</td>
                    <td class="text-end text-nowrap">
                        @if (in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true))
                            <a href="{{ route('students.documents.show', [$student, $document, 'preview' => 1]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ __('students.document_preview') }}</a>
                        @endif
                        <a href="{{ route('students.documents.show', [$student, $document]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-download" aria-hidden="true"></i> {{ __('app.download') }}
                        </a>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('students.documents.destroy', [$student, $document]) }}" class="d-inline" data-confirm="{{ __('students.confirm_document_remove') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('app.delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('students.no_documents') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
