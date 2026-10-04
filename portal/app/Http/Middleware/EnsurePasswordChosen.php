<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone signed in with a temporary password goes nowhere until they
 * have chosen their own. Runs on every web request, so no route can be
 * forgotten.
 */
class EnsurePasswordChosen
{
    private const ALLOWED = ['account.welcome', 'account.choose-password', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status === 'invited' && ! $request->routeIs(...self::ALLOWED)) {
            return redirect()->route('account.welcome');
        }

        return $next($request);
    }
}
