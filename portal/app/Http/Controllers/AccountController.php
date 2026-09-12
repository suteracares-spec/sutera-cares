<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Every signed-in user manages their own account here, whatever their
 * role. Password changes need the current password, so a walked-away
 * unlocked laptop cannot be used to lock the real owner out.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.edit', ['user' => Auth::user()]);
    }

    public function updateDetails(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:150'],
            'email'  => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:30'],
            'locale' => ['required', 'in:en,ms,zh'],
        ]);

        $user->update($data);

        $this->audit($request, 'updated_profile');

        return back()->with('status', 'Your details have been saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => [
                'required',
                'confirmed',
                // 12 characters, and checked against known breached
                // passwords. This system holds health records; "Abcd1234!"
                // satisfies a complexity rule and is still guessed first.
                Password::min(12)->uncompromised(),
                'different:current_password',
            ],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'password.different'                => 'The new password must be different from the current one.',
            'password.uncompromised'            => 'That password appears in known data breaches. Choose another.',
        ]);

        $user->forceFill(['password' => Hash::make($request->string('password')->toString())])->save();

        // Anyone signed in as this user elsewhere is signed out. If the
        // password was changed because someone else had it, leaving their
        // session alive would defeat the point.
        Auth::logoutOtherDevices($request->string('password')->toString());

        $this->audit($request, 'password_changed');

        return back()->with('status',
            'Password changed. Any other device signed in as you has been signed out.');
    }

    private function audit(Request $request, string $action): void
    {
        AuditLog::create([
            'user_id'      => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'user',
            'subject_id'   => $request->user()->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 300),
        ]);
    }
}
