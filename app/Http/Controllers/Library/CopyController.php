<?php

namespace App\Http\Controllers\Library;

use App\Enums\CopyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\CopyRequest;
use App\Models\Book;
use App\Models\BookCopy;
use App\Services\LibraryService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** جلدهای یک کتاب (مرحله‌ی ۱۷؛ can:library.manage): افزودن چند جلد، ویرایش، خراب/گم/خارج، برگرداندن به «موجود». */
class CopyController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function store(CopyRequest $request, Book $book): RedirectResponse
    {
        $v = $request->validated();
        $copies = $this->library->addCopies($book, (int) $v['copies'], $v['start_accession'] ?? null, $request->copyData(), $request->user());

        return back()->with('success', __('library.copies_added', [
            'count' => Digits::toPersian((string) $copies->count()),
            'from' => Digits::toPersian($copies->first()->accession_no),
            'to' => Digits::toPersian($copies->last()->accession_no),
        ]));
    }

    public function update(CopyRequest $request, BookCopy $copy): RedirectResponse
    {
        $this->library->updateCopy($copy, ['accession_no' => $request->validated()['accession_no']] + $request->copyData(), $request->user());

        return back()->with('success', __('library.saved'));
    }

    public function status(Request $request, BookCopy $copy): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([CopyStatus::Available->value, CopyStatus::Damaged->value, CopyStatus::Lost->value, CopyStatus::Withdrawn->value])],
            'reason' => ['nullable', 'string', 'max:300'],
        ], [], __('library.attributes'));
        $this->library->setCopyStatus($copy, CopyStatus::from($data['status']), $data['reason'] ?? null, $request->user());

        return back()->with('success', __('library.copy_status_saved'));
    }
}
