<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sutera Care Provider — portal routes
|--------------------------------------------------------------------------
| Four roles, four products. The role middleware decides which product a
| signed-in user sees; record-level access is checked in the controllers.
*/

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// ---- Office ---------------------------------------------------------
Route::middleware(['auth', 'role:admin,coordinator'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::resource('patients', PatientController::class);
    });

// ---- Caregiver, on a phone, in someone's home -----------------------
Route::middleware(['auth', 'role:caregiver'])
    ->prefix('caregiver')->name('caregiver.')->group(function () {
        Route::view('/', 'placeholder', ['area' => 'Caregiver'])->name('dashboard');
    });

// ---- Family ---------------------------------------------------------
Route::middleware(['auth', 'role:guardian'])
    ->prefix('family')->name('guardian.')->group(function () {
        Route::view('/', 'placeholder', ['area' => 'Family'])->name('dashboard');
    });

// ---- The person receiving care --------------------------------------
Route::middleware(['auth', 'role:patient'])
    ->prefix('my-care')->name('patient.')->group(function () {
        Route::view('/', 'placeholder', ['area' => 'My care'])->name('dashboard');
    });
