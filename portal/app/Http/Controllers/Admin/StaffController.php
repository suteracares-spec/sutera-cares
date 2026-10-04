<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Office accounts, administrators only. Coordinators run the day; an
 * administrator also sees the System page and the audit log, and manages
 * these accounts. A leaver is suspended, never deleted: their name stays
 * on what they did.
 */
class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff', [
            'staff' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_COORDINATOR])
                ->orderByRaw("status = 'suspended'")->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role'  => ['required', Rule::in([User::ROLE_COORDINATOR, User::ROLE_ADMIN])],
        ]);

        $password = SignInController::generate();
        $user = User::create($data + ['password' => Hash::make($password), 'status' => 'invited']);

        AuditLog::record($request, 'staff_created', 'user', $user->id, $user->role);

        return back()->with('temp_password', [
            'name' => $user->name, 'email' => $user->email, 'password' => $password, 'suspended' => false,
        ]);
    }

    /** Suspend or restore. Suspending signs them out everywhere at once. */
    public function status(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        abort_if($user->is($request->user()), 403, 'You cannot suspend yourself.');

        $suspend = $user->status !== 'suspended';

        if ($suspend && $user->isAdmin()
            && User::where('role', User::ROLE_ADMIN)->where('status', '!=', 'suspended')->count() <= 1) {
            return back()->withErrors(['staff' => 'That is the last active administrator.']);
        }

        DB::transaction(function () use ($user, $suspend) {
            $user->forceFill(['status' => $suspend ? 'suspended' : ($user->last_login_at ? 'active' : 'invited')])->save();
            if ($suspend && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        AuditLog::record($request, $suspend ? 'staff_suspended' : 'staff_restored', 'user', $user->id);

        return back()->with('status', $suspend ? "{$user->name} is suspended and signed out." : "{$user->name} can sign in again.");
    }

    /** For a lost phone with no recovery codes left: they set up two-factor again at next sign-in. */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null,
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        AuditLog::record($request, 'two_factor_reset', 'user', $user->id);

        return back()->with('status', "{$user->name}'s two-factor sign-in is reset. They set it up again when they next sign in.");
    }
}
