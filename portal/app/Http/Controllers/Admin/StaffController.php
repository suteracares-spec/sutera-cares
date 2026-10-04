<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Office accounts, administrators only. Coordinators run the day; an
 * administrator also sees the System page and the audit log, and manages
 * these accounts. The rules live in StaffActions, shared with the app.
 */
class StaffController extends Controller
{
    public function __construct(private StaffActions $actions) {}

    public function index(): View
    {
        return view('admin.staff', [
            'staff' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_COORDINATOR])
                ->orderByRaw("status = 'suspended'")->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $password] = $this->actions->create($request);

        return back()->with('temp_password', [
            'name' => $user->name, 'email' => $user->email, 'password' => $password, 'suspended' => false,
        ]);
    }

    /** Suspend or restore. */
    public function status(Request $request, User $user): RedirectResponse
    {
        return back()->with('status', $this->actions->toggleStatus($request, $user));
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        return back()->with('status', $this->actions->resetTwoFactor($request, $user));
    }
}
