<?php

namespace App\Services;

use App\Http\Controllers\Admin\SignInController;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Office accounts, shared by the website and the app. A leaver is
 * suspended, never deleted: their name stays on what they did.
 */
class StaffActions
{
    /** @return array{0: User, 1: string} the new account and its temporary password */
    public function create(Request $request): array
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

        return [$user, $password];
    }

    /** Suspend or restore. Suspending signs them out everywhere at once, the app included. */
    public function toggleStatus(Request $request, User $user): string
    {
        abort_unless($user->isStaff(), 404);
        abort_if($user->is($request->user()), 403, 'You cannot suspend yourself.');

        $suspend = $user->status !== 'suspended';

        if ($suspend && $user->isAdmin()
            && User::where('role', User::ROLE_ADMIN)->where('status', '!=', 'suspended')->count() <= 1) {
            throw ValidationException::withMessages(['staff' => 'That is the last active administrator.']);
        }

        DB::transaction(function () use ($user, $suspend) {
            $user->forceFill(['status' => $suspend ? 'suspended' : ($user->last_login_at ? 'active' : 'invited')])->save();
            if ($suspend) {
                $this->signOutEverywhere($user);
            }
        });

        AuditLog::record($request, $suspend ? 'staff_suspended' : 'staff_restored', 'user', $user->id);

        return $suspend ? "{$user->name} is suspended and signed out." : "{$user->name} can sign in again.";
    }

    /** For a lost phone with no recovery codes left: they set up two-factor again at next sign-in. */
    public function resetTwoFactor(Request $request, User $user): string
    {
        abort_unless($user->isStaff(), 404);

        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null,
        ])->save();
        $this->signOutEverywhere($user);

        AuditLog::record($request, 'two_factor_reset', 'user', $user->id);

        return "{$user->name}'s two-factor sign-in is reset. They set it up again when they next sign in.";
    }

    private function signOutEverywhere(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        ApiToken::where('user_id', $user->id)->delete();
    }
}
