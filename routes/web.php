<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\WorkHistoryController;
use App\Http\Controllers\Students\GuardianController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentDocumentController;
use Illuminate\Support\Facades\Route;

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

        // ---------- تنظیمات ----------
        Route::prefix('settings')->name('settings.')->group(function () {
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
