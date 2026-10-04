<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A second factor for office staff: a six-digit code from an authenticator
 * app, asked for once per sign-in. Setting it up needs the app to prove it
 * produces the right codes before the secret is saved, so a mistyped key
 * cannot lock anyone out. Recovery codes are the way back from a lost
 * phone; each works once.
 */
class TwoFactorController extends Controller
{
    public const SESSION_KEY = 'two_factor_passed';

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactor()) {
            return redirect()->route($user->homeRoute());
        }

        // The secret lives in the session until confirmed, so an abandoned
        // setup leaves nothing half-saved on the account.
        $secret = $request->session()->get('two_factor_pending') ?? Totp::generateSecret();
        $request->session()->put('two_factor_pending', $secret);

        return view('auth.two-factor-setup', [
            'readable' => Totp::readable($secret),
            'uri'      => Totp::uri($secret, $user->email, 'Sutera Care Provider'),
        ]);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get('two_factor_pending');

        if ($user->hasTwoFactor() || ! $secret) {
            return redirect()->route('two-factor.setup');
        }

        $request->validate(['code' => ['required', 'string']]);

        if (! Totp::verify($secret, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Check the app shows "Sutera Care Provider" and try the newest code.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)))->all();

        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();

        $request->session()->forget('two_factor_pending');
        $request->session()->put(self::SESSION_KEY, true);
        AuditLog::record($request, 'two_factor_enabled', 'user', $user->id);

        // Shown once. They are stored only as hashes.
        return view('auth.two-factor-codes', ['codes' => $codes]);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $code = trim($request->string('code'));

        $ok = Totp::verify($user->two_factor_secret, $code);
        $usedRecovery = false;

        if (! $ok && strlen($code) > 6) {
            // A recovery code, then: consume it so it cannot be used twice.
            $remaining = [];
            foreach ($user->two_factor_recovery_codes ?? [] as $hash) {
                if (! $usedRecovery && Hash::check(Str::lower($code), $hash)) {
                    $usedRecovery = true;
                    continue;
                }
                $remaining[] = $hash;
            }
            if ($usedRecovery) {
                $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
                $ok = true;
            }
        }

        if (! $ok) {
            AuditLog::record($request, 'two_factor_failed', 'user', $user->id);
            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        $request->session()->put(self::SESSION_KEY, true);
        AuditLog::record($request, $usedRecovery ? 'two_factor_recovery_used' : 'two_factor_passed', 'user', $user->id);

        $left = count($user->two_factor_recovery_codes ?? []);

        return redirect()->intended(route($user->homeRoute()))->with('status', $usedRecovery
            ? "Signed in with a recovery code. {$left} left. If your phone is lost, ask an administrator to reset your two-factor sign-in."
            : null);
    }
}
