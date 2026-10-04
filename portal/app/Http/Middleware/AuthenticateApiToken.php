<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token sign-in for the app. The same rules as the website: a
 * suspended account is refused outright, and an account still on its
 * temporary password can do nothing but choose its own.
 */
class AuthenticateApiToken
{
    private const ALLOWED_WHILE_INVITED = ['api.password', 'api.logout', 'api.me'];

    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findValid($request->bearerToken());
        $user = $token?->user;

        if (! $user || $user->status === 'suspended') {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        if ($user->status === 'invited' && ! $request->routeIs(...self::ALLOWED_WHILE_INVITED)) {
            return response()->json(['message' => 'Choose your own password first.', 'password_change_required' => true], 403);
        }

        // Once a minute is enough to show when a phone was last seen.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
