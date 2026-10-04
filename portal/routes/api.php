<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Caregiver app API: /portal/api/v1/...
|--------------------------------------------------------------------------
| Bearer tokens, no cookies, no CSRF (there is no browser session to
| ride on). Same access rules as the website: a caregiver sees only the
| shifts they are doing.
*/

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['api.token', 'throttle:120,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('password', [AuthController::class, 'password'])->middleware('throttle:6,1')->name('password');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::post('shifts/{shift}/check-in', [ShiftController::class, 'checkIn'])->name('shifts.check-in');
        Route::post('shifts/{shift}/check-out', [ShiftController::class, 'checkOut'])->name('shifts.check-out');

        // Office overview: administrators and coordinators, read-only.
        Route::middleware('role:admin,coordinator')->prefix('office')->name('office.')->group(function () {
            Route::get('day', [OfficeController::class, 'day'])->name('day');
            Route::get('shifts/{shift}', [OfficeController::class, 'shift'])->name('shift');
            Route::get('concerns', [OfficeController::class, 'concerns'])->name('concerns');
            Route::get('concerns/{concern}', [OfficeController::class, 'concern'])->name('concern');
        });
    });
});
