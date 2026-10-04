<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Services\FamilyActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
    public function issue(Request $request, User $user, FamilyActions $actions): RedirectResponse
    {
        return back()->with('temp_password', $actions->issueTemporaryPassword($request, $user));
    }

    /**
     * Give a client their own sign-in to the small "my care" view. Most
     * clients never need one; their family does the looking.
     */
    public function clientLogin(Request $request, Patient $patient, FamilyActions $actions): RedirectResponse
    {
        return back()->with('temp_password', $actions->createClientLogin($request, $patient));
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
