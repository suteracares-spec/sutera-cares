<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\CaregiverController;
use App\Http\Controllers\Admin\ConcernController;
use App\Http\Controllers\Admin\CarePlanController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SignInController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Caregiver\VisitController;
use App\Http\Controllers\Family\FamilyController;
use App\Http\Controllers\Family\MyCareController;
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
Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->homeRoute())
    : redirect()->route('login'));

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
    Route::get('/welcome', [AccountController::class, 'welcome'])->name('welcome');
    Route::post('/welcome', [AccountController::class, 'choosePassword'])
        ->middleware('throttle:6,1')->name('choose-password');
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

        // Care plans and family members belong to a client. Shallow, so a
        // plan or a family link is addressed by its own id once it exists.
        Route::post('patients/{patient}/care-plans', [CarePlanController::class, 'store'])->name('care-plans.store');
        Route::resource('care-plans', CarePlanController::class)
            ->only(['show', 'edit', 'update', 'destroy'])
            ->parameters(['care-plans' => 'carePlan']);
        Route::post('care-plans/{carePlan}/activate', [CarePlanController::class, 'activate'])->name('care-plans.activate');

        Route::get('patients/{patient}/family/create', [GuardianController::class, 'create'])->name('guardians.create');
        Route::post('patients/{patient}/family', [GuardianController::class, 'store'])->name('guardians.store');
        Route::resource('family', GuardianController::class)
            ->only(['edit', 'update', 'destroy'])
            ->parameters(['family' => 'guardian'])
            ->names('guardians');

        // Placing caregivers and booking their time.
        Route::get('schedule', ScheduleController::class)->name('schedule');
        Route::get('patients/{patient}/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('patients/{patient}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::put('assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::post('assignments/{assignment}/end', [AssignmentController::class, 'end'])->name('assignments.end');
        Route::post('assignments/{assignment}/shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::post('assignments/{assignment}/shifts/generate', [ShiftController::class, 'generate'])->name('shifts.generate');
        Route::get('shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
        Route::post('shifts/{shift}/cancel', [ShiftController::class, 'cancel'])->name('shifts.cancel');
        Route::post('shifts/{shift}/cover', [ShiftController::class, 'cover'])->name('shifts.cover');
        Route::post('shifts/{shift}/correct', [ShiftController::class, 'correct'])->name('shifts.correct');
        Route::post('shifts/{shift}/missed', [ShiftController::class, 'missed'])->name('shifts.missed');

        // Billing: invoices from completed shifts, payments recorded by hand.
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/prepare', [InvoiceController::class, 'prepare'])->name('invoices.prepare');
        Route::post('invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::post('invoices/{invoice}/lines', [InvoiceController::class, 'addLine'])->name('invoices.lines.store');
        Route::delete('invoices/{invoice}/lines/{line}', [InvoiceController::class, 'removeLine'])->name('invoices.lines.destroy');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

        Route::get('concerns', [ConcernController::class, 'index'])->name('concerns.index');
        Route::get('concerns/{concern}', [ConcernController::class, 'show'])->name('concerns.show');
        Route::put('concerns/{concern}', [ConcernController::class, 'update'])->name('concerns.update');

        Route::post('patients/{patient}/sign-in', [SignInController::class, 'clientLogin'])
            ->middleware('throttle:20,1')->name('patients.sign-in');
        Route::post('users/{user}/temporary-password', [SignInController::class, 'issue'])
            ->middleware('throttle:20,1')->name('users.temp-password');

        Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::patch('enquiries/{enquiry}/status', [EnquiryController::class, 'updateStatus'])->name('enquiries.status');
        Route::post('enquiries/{enquiry}/convert', [EnquiryController::class, 'convert'])->name('enquiries.convert');
    });

// ---- Administrators only --------------------------------------------
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin/system')->name('admin.')->group(function () {
        Route::get('/', [SystemController::class, 'index'])->name('system');
        Route::post('/migrate', [SystemController::class, 'migrate'])->name('system.migrate');
    });

// ---- Caregiver, on a phone, in someone's home -----------------------
Route::middleware(['auth', 'role:caregiver'])
    ->prefix('caregiver')->name('caregiver.')->group(function () {
        Route::get('/', [VisitController::class, 'index'])->name('dashboard');
        Route::get('/shifts/{shift}', [VisitController::class, 'show'])->name('shifts.show');
        Route::post('/shifts/{shift}/check-in', [VisitController::class, 'checkIn'])
            ->middleware('throttle:20,1')->name('shifts.check-in');
        Route::post('/shifts/{shift}/check-out', [VisitController::class, 'checkOut'])
            ->middleware('throttle:20,1')->name('shifts.check-out');
    });

// ---- Family ---------------------------------------------------------
Route::middleware(['auth', 'role:guardian'])
    ->prefix('family')->name('guardian.')->group(function () {
        Route::get('/', [FamilyController::class, 'index'])->name('dashboard');
        Route::get('/clients/{patient}', [FamilyController::class, 'show'])->name('clients.show');
        Route::post('/clients/{patient}/concerns', [FamilyController::class, 'concern'])
            ->middleware('throttle:10,1')->name('clients.concern');
        Route::get('/invoices/{invoice}', [FamilyController::class, 'invoice'])->name('invoices.show');
    });

// ---- The person receiving care --------------------------------------
Route::middleware(['auth', 'role:patient'])
    ->prefix('my-care')->name('patient.')->group(function () {
        Route::get('/', [MyCareController::class, 'show'])->name('dashboard');
        Route::post('/concerns', [MyCareController::class, 'concern'])->middleware('throttle:10,1')->name('concern');
    });
