<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OfficeActionsController;
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

        // Office: administrators and coordinators.
        Route::middleware('role:admin,coordinator')->prefix('office')->name('office.')->group(function () {
            Route::get('day', [OfficeController::class, 'day'])->name('day');
            Route::get('shifts/{shift}', [OfficeController::class, 'shift'])->name('shift');
            Route::get('concerns', [OfficeController::class, 'concerns'])->name('concerns');
            Route::get('concerns/{concern}', [OfficeController::class, 'concern'])->name('concern');

            Route::get('week', [OfficeActionsController::class, 'week'])->name('week');
            Route::get('shifts/{shift}/relief', [OfficeActionsController::class, 'relief'])->name('shift.relief');
            Route::post('shifts/{shift}/move', [OfficeActionsController::class, 'move'])->name('shift.move');
            Route::post('shifts/{shift}/cancel', [OfficeActionsController::class, 'cancel'])->name('shift.cancel');
            Route::post('shifts/{shift}/cover', [OfficeActionsController::class, 'cover'])->name('shift.cover');
            Route::post('shifts/{shift}/missed', [OfficeActionsController::class, 'missed'])->name('shift.missed');
            Route::post('shifts/{shift}/correct', [OfficeActionsController::class, 'correct'])->name('shift.correct');

            Route::get('staff', [OfficeActionsController::class, 'staff'])->name('staff');
            Route::put('concerns/{concern}', [OfficeActionsController::class, 'updateConcern'])->name('concern.update');

            Route::get('enquiries', [OfficeActionsController::class, 'enquiries'])->name('enquiries');
            Route::get('enquiries/{enquiry}', [OfficeActionsController::class, 'enquiry'])->name('enquiry');
            Route::post('enquiries/{enquiry}/status', [OfficeActionsController::class, 'enquiryStatus'])->name('enquiry.status');
            Route::post('enquiries/{enquiry}/convert', [OfficeActionsController::class, 'convertEnquiry'])->name('enquiry.convert');

            Route::get('client-options', [ClientController::class, 'options'])->name('client-options');
            Route::get('clients', [ClientController::class, 'index'])->name('clients');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
            Route::get('clients/{patient}', [ClientController::class, 'show'])->name('clients.show');
            Route::put('clients/{patient}', [ClientController::class, 'update'])->name('clients.update');
            Route::delete('clients/{patient}', [ClientController::class, 'destroy'])->name('clients.destroy');
            Route::post('clients/{patient}/care-plans', [ClientController::class, 'startPlan'])->name('care-plans.start');
            Route::get('care-plans/{carePlan}', [ClientController::class, 'showPlan'])->name('care-plans.show');
            Route::put('care-plans/{carePlan}', [ClientController::class, 'savePlan'])->name('care-plans.save');
            Route::post('care-plans/{carePlan}/activate', [ClientController::class, 'activatePlan'])->name('care-plans.activate');
            Route::delete('care-plans/{carePlan}', [ClientController::class, 'discardPlan'])->name('care-plans.discard');
            Route::post('clients/{patient}/family', [ClientController::class, 'addFamily'])->name('family.add');
            Route::put('family/{guardian}', [ClientController::class, 'updateFamily'])->name('family.update');
            Route::delete('family/{guardian}', [ClientController::class, 'removeFamily'])->name('family.remove');
            Route::post('clients/{patient}/sign-in', [ClientController::class, 'clientLogin'])->middleware('throttle:20,1')->name('clients.sign-in');
            Route::post('users/{user}/temporary-password', [ClientController::class, 'temporaryPassword'])->middleware('throttle:20,1')->name('users.temp-password');
        });
    });
});
