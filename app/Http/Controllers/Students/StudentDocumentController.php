<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentDocumentRequest;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\ActivityLogger;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** اسناد دانش‌آموز (تذکره، اسناد مکتب قبلی …) — فقط برای کاربر دارای students.manage. */
class StudentDocumentController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function store(StudentDocumentRequest $request, Student $student): RedirectResponse
    {
        $file = $request->file('file');
        $path = PrivateFiles::store($file, 'students/documents/'.$student->id);

        try {
            DB::transaction(function () use ($request, $student, $file, $path) {
                $document = $student->documents()->create([
                    'title' => $request->validated('title'),
                    'file_path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
                $this->logger->log('student.document_added', $student, $student->full_name.' — '.$document->title);
            });
        } catch (\Throwable $e) {
            PrivateFiles::delete($path);
            throw $e;
        }

        return redirect()->to(route('students.show', $student).'#documents')->with('success', __('students.document_added'));
    }

    public function show(Request $request, Student $student, StudentDocument $document): StreamedResponse
    {
        Gate::authorize('viewSensitive', Student::class);
        abort_unless($document->student_id === $student->id && PrivateFiles::exists($document->file_path), 404);

        return PrivateFiles::download($document->file_path, $document->original_name);
    }

    public function destroy(Student $student, StudentDocument $document): RedirectResponse
    {
        Gate::authorize('update', $student);
        abort_unless($document->student_id === $student->id, 404);

        DB::transaction(function () use ($student, $document) {
            $document->delete();
            $this->logger->log('student.document_removed', $student, $student->full_name.' — '.$document->title,
                old: ['title' => $document->title, 'file' => $document->original_name]);
        });
        PrivateFiles::delete($document->file_path);

        return redirect()->to(route('students.show', $student).'#documents')->with('success', __('students.document_removed'));
    }
}
