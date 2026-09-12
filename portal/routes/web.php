<?php

use App\Http\Controllers\Admin\CaregiverController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\IntakeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sutera Care Provider — portal routes
|--------------------------------------------------------------------------
| Four roles, four products. The role middleware decides which product a
| signed-in user sees; record-level access is checked in the controllers,
| because hiding a link is not access control.
*/

// Named route, not a literal path. The portal is served from a
// subdirectory (/portal), and a literal '/login' would send visitors to
// the site root, where no such page exists.
Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// ---- Your own account, whatever your role ---------------------------
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'edit'])->name('edit');
    Route::put('/details', [AccountController::class, 'updateDetails'])->name('details');
    Route::put('/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')->name('password');
});

// ---- Public intake --------------------------------------------------
// The only unauthenticated writes in the portal. Rate limited hard,
// because a public form is a public form.
Route::middleware('throttle:6,1')->prefix('intake')->name('intake.')->group(function () {
    Route::post('/enquiry', [IntakeController::class, 'enquiry'])->name('enquiry');
    Route::post('/application', [IntakeController::class, 'application'])->name('application');
});

// ---- Office ---------------------------------------------------------
Route::middleware(['auth', 'role:admin,coordinator'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');

        Route::resource('patients', PatientController::class);
        Route::resource('caregivers', CaregiverController::class);

        Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::patch('enquiries/{enquiry}/status', [EnquiryController::class, 'updateStatus'])->name('enquiries.status');
        Route::post('enquiries/{enquiry}/convert', [EnquiryController::class, 'convert'])->name('enquiries.convert');
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
