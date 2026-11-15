<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\UserController;
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
