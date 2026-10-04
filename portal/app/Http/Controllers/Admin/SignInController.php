<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Issuing a temporary password, because the server sends no email.
 *
 * The office passes it on by WhatsApp or in person. It is shown once and
 * stored only as a hash. The account goes back to "invited", which forces
 * the person to choose their own password on first sign-in, so the
 * temporary one is only ever good for that single step.
 */
class SignInController extends Controller
{
    public function issue(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // Coordinators set up clients, families and caregivers. Staff
        // accounts are the keys to everything, so only an administrator
        // can reset one, and nobody resets their own this way.
        abort_if($user->is($actor), 403, 'Change your own password on your account page.');
        abort_if($user->isStaff() && ! $actor->isAdmin(), 403, 'Only an administrator can reset a staff account.');

        $password = self::generate();

        DB::transaction(function () use ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                // A suspended account stays suspended; the password is
                // ready for when access is restored.
                'status'   => $user->status === 'suspended' ? 'suspended' : 'invited',
            ])->save();

            // Anyone still signed in as them is signed out.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        AuditLog::record($request, 'temp_password_issued', 'user', $user->id, $user->role);

        return back()->with('temp_password', [
            'name'      => $user->name,
            'email'     => $user->email,
            'password'  => $password,
            'suspended' => $user->status === 'suspended',
        ]);
    }

    /**
     * Give a client their own sign-in to the small "my care" view. Most
     * clients never need one; their family does the looking.
     */
    public function clientLogin(Request $request, Patient $patient): RedirectResponse
    {
        abort_if($patient->user_id !== null, 422, 'This client already has a sign-in.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
        ], ['email.unique' => 'That email already belongs to another account.']);

        $password = self::generate();

        $user = DB::transaction(function () use ($patient, $data, $password) {
            $user = User::create([
                'name'     => $patient->name,
                'email'    => $data['email'],
                'password' => Hash::make($password),
                'role'     => User::ROLE_PATIENT,
                'status'   => 'invited',
            ]);
            $patient->update(['user_id' => $user->id]);

            return $user;
        });

        AuditLog::record($request, 'client_login_created', 'patient', $patient->id, $patient->code);

        return back()->with('temp_password', [
            'name' => $user->name, 'email' => $user->email, 'password' => $password, 'suspended' => false,
        ]);
    }

    /**
     * Easy to read aloud and type on a phone: lowercase, no 0/o or 1/l,
     * grouped in fours. 12 random characters from 31 is ~59 bits, and it
     * only has to survive until first sign-in, behind a rate limit.
     */
    public static function generate(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $chars = '';
        for ($i = 0; $i < 12; $i++) {
            $chars .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return implode('-', str_split($chars, 4));
    }
}
