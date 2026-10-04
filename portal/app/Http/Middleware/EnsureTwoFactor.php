<?php

namespace App\Http\Middleware;

use App\Http\Controllers\TwoFactorController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Office staff pass a second factor once per sign-in before anything
 * else, and set one up first if they have none. Runs on every web
 * request, after the choose-a-password step, so no route is missed.
 */
class EnsureTwoFactor
{
    private const ALLOWED = [
        'two-factor.*', 'logout', 'account.welcome', 'account.choose-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->needsTwoFactor() || $user->status === 'invited' || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if (! $user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        if (! $request->session()->get(TwoFactorController::SESSION_KEY)) {
            // Remember where they were going, as a sign-in would.
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
