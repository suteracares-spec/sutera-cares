<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate whole areas of the portal by role.
 *
 * Used as `role:admin,coordinator` on a route group. This is a coarse
 * first gate — it decides which product you see. Record-level access
 * (which patients THIS caregiver may open) is a separate check in the
 * controllers, because hiding a link is not access control.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // A suspended account keeps its password but loses the building.
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account is not active. Contact your coordinator.']);
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have access to that part of the portal.');
        }

        return $next($request);
    }
}
