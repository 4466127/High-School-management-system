<?php

use App\Http\Controllers\Academics\AcademicYearController;
use App\Http\Controllers\Academics\GradeSubjectController;
use App\Http\Controllers\Academics\PromotionController;
use App\Http\Controllers\Academics\SectionController;
use App\Http\Controllers\Attendance\AttendanceReportController;
use App\Http\Controllers\Attendance\DailyAttendanceController;
use App\Http\Controllers\Attendance\StaffAttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Courses\CourseAttendanceController;
use App\Http\Controllers\Courses\CourseController;
use App\Http\Controllers\Courses\CourseLedgerController;
use App\Http\Controllers\Courses\ParticipantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\MarkController;
use App\Http\Controllers\Exams\ReexamController;
use App\Http\Controllers\Exams\ReportCardController;
use App\Http\Controllers\Exams\ResultController;
use App\Http\Controllers\Finance\DebtorController;
use App\Http\Controllers\Finance\FeeSetupController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\LedgerController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\SchoolSettingController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\WorkHistoryController;
use App\Http\Controllers\Students\EnrollmentController;
use App\Http\Controllers\Students\GuardianController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentDocumentController;
use Illuminate\Support\Facades\Route;

// لوگوی مکتب: برای همه (صفحه‌ی ورود هم نشان می‌دهد)
Route::get('/school-logo', [SchoolSettingController::class, 'logo'])->name('school.logo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        // ---------- دانش‌آموزان (مجوز دقیق در StudentPolicy) ----------
        Route::middleware('can:students.view')->prefix('students')->name('students.')->group(function () {
            Route::get('/', [StudentController::class, 'index'])->name('index');
            Route::get('/create', [StudentController::class, 'create'])->name('create');
            Route::post('/', [StudentController::class, 'store'])->name('store');
            Route::get('/{student}', [StudentController::class, 'show'])->withTrashed()->name('show');
            Route::get('/{student}/edit', [StudentController::class, 'edit'])->name('edit');
            Route::put('/{student}', [StudentController::class, 'update'])->name('update');
            Route::patch('/{student}/status', [StudentController::class, 'changeStatus'])->name('status');
            Route::delete('/{student}', [StudentController::class, 'destroy'])->name('destroy');
            Route::patch('/{student}/restore', [StudentController::class, 'restore'])->withTrashed()->name('restore');
            Route::get('/{student}/photo', [StudentController::class, 'photo'])->withTrashed()->name('photo');

            Route::post('/{student}/guardians', [GuardianController::class, 'store'])->name('guardians.store');
            Route::put('/{student}/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
            Route::delete('/{student}/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');

            Route::post('/{student}/enrollment', [EnrollmentController::class, 'store'])->name('enrollment.store');
            Route::patch('/{student}/enrollment/move', [EnrollmentController::class, 'move'])->name('enrollment.move');
            Route::patch('/{student}/enrollment/leave', [EnrollmentController::class, 'leave'])->name('enrollment.leave');

            Route::post('/{student}/documents', [StudentDocumentController::class, 'store'])->name('documents.store');
            Route::get('/{student}/documents/{document}', [StudentDocumentController::class, 'show'])->name('documents.show');
            Route::delete('/{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])->name('documents.destroy');
        });

        // ---------- معلمان و کارکنان ----------
        Route::middleware('can:staff.manage')->prefix('staff')->name('staff.')->group(function () {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::get('/create', [StaffController::class, 'create'])->name('create');
            Route::post('/', [StaffController::class, 'store'])->name('store');
            Route::get('/{staff}', [StaffController::class, 'show'])->withTrashed()->name('show');
            Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
            Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
            Route::patch('/{staff}/status', [StaffController::class, 'changeStatus'])->name('status');
            Route::delete('/{staff}', [StaffController::class, 'destroy'])->name('destroy');
            Route::patch('/{staff}/restore', [StaffController::class, 'restore'])->withTrashed()->name('restore');
            Route::get('/{staff}/photo', [StaffController::class, 'photo'])->withTrashed()->name('photo');

            Route::post('/{staff}/history', [WorkHistoryController::class, 'store'])->name('history.store');
            Route::delete('/{staff}/history/{history}', [WorkHistoryController::class, 'destroy'])->name('history.destroy');
        });

        // ---------- صنف‌ها و مضامین (مجوز دقیق بخش‌ها در SectionPolicy) ----------
        Route::prefix('academics')->name('academics.')->group(function () {
            Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
            Route::get('/sections/create', [SectionController::class, 'create'])->name('sections.create');
            Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
            Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
            Route::get('/sections/{section}/edit', [SectionController::class, 'edit'])->name('sections.edit');
            Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
            Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
            Route::put('/sections/{section}/assignments', [SectionController::class, 'updateAssignments'])->name('sections.assignments');
            Route::get('/sections/{section}/enroll', [SectionController::class, 'enrollForm'])->name('sections.enroll');
            Route::post('/sections/{section}/enroll', [SectionController::class, 'enroll'])->name('sections.enroll.store');
            Route::patch('/sections/{section}/renumber', [SectionController::class, 'renumber'])->name('sections.renumber');

            Route::middleware('can:academics.manage')->group(function () {
                Route::get('/years', [AcademicYearController::class, 'index'])->name('years.index');
                Route::get('/years/create', [AcademicYearController::class, 'create'])->name('years.create');
                Route::post('/years', [AcademicYearController::class, 'store'])->name('years.store');
                Route::get('/years/{year}/edit', [AcademicYearController::class, 'edit'])->name('years.edit');
                Route::put('/years/{year}', [AcademicYearController::class, 'update'])->name('years.update');
                Route::patch('/years/{year}/current', [AcademicYearController::class, 'setCurrent'])->name('years.current');
                Route::post('/years/{year}/terms', [AcademicYearController::class, 'storeTerm'])->name('years.terms.store');
                Route::put('/years/{year}/terms/{term}', [AcademicYearController::class, 'updateTerm'])->name('years.terms.update');
                Route::delete('/years/{year}/terms/{term}', [AcademicYearController::class, 'destroyTerm'])->name('years.terms.destroy');

                Route::get('/grades', [GradeSubjectController::class, 'index'])->name('grades.index');
                Route::post('/grades', [GradeSubjectController::class, 'storeGrade'])->name('grades.store');
                Route::put('/grades/{grade}', [GradeSubjectController::class, 'updateGrade'])->name('grades.update');
                Route::get('/grades/{grade}/subjects', [GradeSubjectController::class, 'editSubjects'])->name('grades.subjects');
                Route::put('/grades/{grade}/subjects', [GradeSubjectController::class, 'updateSubjects'])->name('grades.subjects.update');
                Route::post('/subjects', [GradeSubjectController::class, 'storeSubject'])->name('subjects.store');
                Route::put('/subjects/{subject}', [GradeSubjectController::class, 'updateSubject'])->name('subjects.update');
            });

            Route::middleware('can:students.manage')->group(function () {
                Route::get('/promotion', [PromotionController::class, 'create'])->name('promotion.create');
                Route::post('/promotion', [PromotionController::class, 'store'])->name('promotion.store');
            });
        });

        // ---------- حاضری (مجوز دقیق در AttendancePolicy) ----------
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [DailyAttendanceController::class, 'index'])->name('daily.index');
            Route::get('/export', [DailyAttendanceController::class, 'export'])->name('daily.export');
            Route::get('/sections/{section}', [DailyAttendanceController::class, 'show'])->name('daily.show');
            Route::post('/sections/{section}', [DailyAttendanceController::class, 'store'])->name('daily.store');

            Route::get('/monthly', [AttendanceReportController::class, 'monthly'])->name('monthly.index');
            Route::get('/monthly/export', [AttendanceReportController::class, 'monthlyExport'])->name('monthly.export');
            Route::get('/absentees', [AttendanceReportController::class, 'absentees'])->name('absentees.index');
            Route::get('/absentees/export', [AttendanceReportController::class, 'absenteesExport'])->name('absentees.export');

            Route::get('/staff', [StaffAttendanceController::class, 'index'])->name('staff.index');
            Route::post('/staff', [StaffAttendanceController::class, 'store'])->name('staff.store');
            Route::get('/staff/monthly', [StaffAttendanceController::class, 'monthly'])->name('staff.monthly');
            Route::get('/staff/monthly/export', [StaffAttendanceController::class, 'monthlyExport'])->name('staff.monthly.export');
        });

        // ---------- امتحانات، نمرات، کارنامه (مجوز دقیق در ExamPolicy) ----------
        Route::prefix('exams')->name('exams.')->group(function () {
            Route::middleware('can:exams.manage')->group(function () {
                Route::get('/manage', [ExamController::class, 'index'])->name('manage.index');
                Route::post('/manage', [ExamController::class, 'store'])->name('manage.store');
                Route::get('/manage/{exam}/edit', [ExamController::class, 'edit'])->name('manage.edit');
                Route::put('/manage/{exam}', [ExamController::class, 'update'])->name('manage.update');
                Route::patch('/manage/{exam}/status', [ExamController::class, 'status'])->name('manage.status');
                Route::delete('/manage/{exam}', [ExamController::class, 'destroy'])->name('manage.destroy');
                Route::post('/types', [ExamController::class, 'storeType'])->name('manage.types.store');
                Route::put('/types/{type}', [ExamController::class, 'updateType'])->name('manage.types.update');

                Route::post('/results/{section}/finalize', [ResultController::class, 'finalize'])->name('results.finalize');
                Route::post('/results/{section}/unfinalize', [ResultController::class, 'unfinalize'])->name('results.unfinalize');

                Route::get('/reexam', [ReexamController::class, 'index'])->name('reexam.index');
                Route::post('/reexam/{section}', [ReexamController::class, 'store'])->name('reexam.store');
                Route::post('/reexam/{section}/finalize', [ReexamController::class, 'finalize'])->name('reexam.finalize');
                Route::post('/reexam/{section}/unfinalize', [ReexamController::class, 'unfinalize'])->name('reexam.unfinalize');
            });

            Route::middleware('can:marks.enter')->group(function () {
                Route::get('/marks', [MarkController::class, 'index'])->name('marks.index');
                Route::get('/marks/{exam}/{section}/{subject}', [MarkController::class, 'show'])->name('marks.show');
                Route::post('/marks/{exam}/{section}/{subject}', [MarkController::class, 'store'])->name('marks.store');
            });

            Route::get('/results', [ResultController::class, 'index'])->name('results.index');
            Route::get('/results/export', [ResultController::class, 'export'])->name('results.export');
            Route::get('/report-cards', [ReportCardController::class, 'index'])->name('reportcards.index');
            Route::get('/report-cards/student/{enrollment}', [ReportCardController::class, 'show'])->name('reportcards.show');
            Route::get('/report-cards/section/{section}', [ReportCardController::class, 'section'])->name('reportcards.section');
        });

        // ---------- گزارش‌ها (مجوز reports.academic / reports.finance در ReportController) ----------
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/students', [ReportController::class, 'students'])->name('students.index');
            Route::get('/students/export', [ReportController::class, 'studentsExport'])->name('students.export');
            Route::get('/attendance', [ReportController::class, 'attendance'])->name('attendance.index');
            Route::get('/attendance/export', [ReportController::class, 'attendanceExport'])->name('attendance.export');
            Route::get('/results', [ReportController::class, 'results'])->name('results.index');
            Route::get('/results/export', [ReportController::class, 'resultsExport'])->name('results.export');
            Route::get('/finance', [ReportController::class, 'finance'])->name('finance.index');
            Route::get('/finance/export', [ReportController::class, 'financeExport'])->name('finance.export');
        });

        // ---------- کورس‌های تقویتی (مجوز دقیق در CoursePolicy) ----------
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [CourseController::class, 'index'])->name('list.index');
            Route::get('/create', [CourseController::class, 'create'])->name('list.create');
            Route::post('/', [CourseController::class, 'store'])->name('list.store');
            Route::get('/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('list.show');
            Route::get('/{course}/edit', [CourseController::class, 'edit'])->name('list.edit');
            Route::put('/{course}', [CourseController::class, 'update'])->name('list.update');
            Route::post('/{course}/groups', [CourseController::class, 'storeGroup'])->name('list.groups.store');
            Route::put('/groups/{group}', [CourseController::class, 'updateGroup'])->name('list.groups.update');
            Route::post('/{course}/participants/students', [ParticipantController::class, 'storeStudents'])->name('list.participants.students');
            Route::post('/{course}/participants/external', [ParticipantController::class, 'storeExternal'])->name('list.participants.external');
            Route::put('/participants/{participant}', [ParticipantController::class, 'update'])->name('list.participants.update');

            Route::get('/attendance', [CourseAttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/{group}', [CourseAttendanceController::class, 'show'])->name('attendance.show');
            Route::post('/attendance/{group}', [CourseAttendanceController::class, 'store'])->name('attendance.store');

            Route::get('/finance', [CourseLedgerController::class, 'index'])->name('ledger.index');
            Route::get('/finance/{course}', [CourseLedgerController::class, 'show'])->name('ledger.show');
            Route::get('/finance/{course}/export', [CourseLedgerController::class, 'export'])->name('ledger.export');
            Route::post('/finance/{course}', [CourseLedgerController::class, 'store'])->name('ledger.store');
            Route::patch('/finance/entries/{entry}/void', [CourseLedgerController::class, 'void'])->name('ledger.void');
        });

        // ---------- مالی مکتب (اختیاری؛ فیس: feature:fees، دفتر درآمد و مصرف: feature:ledger) ----------
        Route::prefix('finance')->name('finance.')->middleware('can:finance.view')->group(function () {
            Route::middleware('feature:fees')->group(function () {
                Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
                Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
                Route::get('/payments/new/{student}', [PaymentController::class, 'create'])->name('payments.create');
                Route::post('/payments/new/{student}', [PaymentController::class, 'store'])->name('payments.store');
                Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
                Route::patch('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

                Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
                Route::get('/invoices/issue', [InvoiceController::class, 'create'])->name('invoices.create');
                Route::post('/invoices/issue', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::post('/invoices/student/{student}', [InvoiceController::class, 'storeSingle'])->name('invoices.single');
                Route::patch('/invoices/{invoice}/discount', [InvoiceController::class, 'discount'])->name('invoices.discount');
                Route::patch('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

                Route::get('/debtors', [DebtorController::class, 'index'])->name('debtors.index');
                Route::get('/debtors/export', [DebtorController::class, 'export'])->name('debtors.export');

                Route::middleware('can:finance.manage')->group(function () {
                    Route::get('/setup', [FeeSetupController::class, 'index'])->name('setup.index');
                    Route::post('/setup/types', [FeeSetupController::class, 'storeType'])->name('setup.types.store');
                    Route::put('/setup/types/{feeType}', [FeeSetupController::class, 'updateType'])->name('setup.types.update');
                    Route::put('/setup/rates', [FeeSetupController::class, 'saveRates'])->name('setup.rates');
                });
            });

            Route::middleware('feature:ledger')->group(function () {
                Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
                Route::get('/ledger/export', [LedgerController::class, 'export'])->name('ledger.export');
                Route::post('/ledger', [LedgerController::class, 'store'])->name('ledger.store');
                Route::patch('/ledger/{transaction}/void', [LedgerController::class, 'void'])->name('ledger.void');
                Route::post('/ledger/categories', [LedgerController::class, 'storeCategory'])->name('ledger.categories.store');
                Route::patch('/ledger/categories/{category}', [LedgerController::class, 'toggleCategory'])->name('ledger.categories.toggle');
            });
        });

        // ---------- ورود گروهی از Excel (مجوز هر نوع در ImportController) ----------
        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/', [ImportController::class, 'index'])->name('index');
            Route::get('/template/{type}', [ImportController::class, 'template'])->whereIn('type', ['students', 'staff'])->name('template');
            Route::post('/upload', [ImportController::class, 'upload'])->name('upload');
            Route::get('/preview', [ImportController::class, 'preview'])->name('preview');
            Route::post('/store', [ImportController::class, 'store'])->name('store');
        });

        // ---------- تنظیمات ----------
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::middleware('can:settings.manage')->group(function () {
                Route::get('school', [SchoolSettingController::class, 'edit'])->name('school.edit');
                Route::put('school', [SchoolSettingController::class, 'update'])->name('school.update');
            });

            Route::middleware('can:users.manage')->group(function () {
                Route::resource('users', UserController::class)->except(['show', 'destroy']);
                Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
                Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
            });

            Route::get('activity-logs', ActivityLogController::class)
                ->middleware('can:activity_logs.view')
                ->name('activity-logs.index');
        });
    });
});
