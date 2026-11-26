<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\YearReviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** مرور پایان سال (مرحله‌ی ۱۴؛ exams.manage): گام‌های بستن سال برای هر بخش، با ?print=1 برای چاپ. */
class YearReviewController extends Controller
{
    public function index(Request $request, YearReviewService $review): View
    {
        $year = AcademicYear::current();

        return view('exams.year-review.index', [
            'review' => $year ? $review->review($year) : null,
            'print' => $request->boolean('print'),
        ]);
    }
}
