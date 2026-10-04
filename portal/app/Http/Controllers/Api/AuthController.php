<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in for the caregiver app. The app is for caregivers only: office
 * staff need two-factor, which the app does not do, and families have the
 * website. Same throttling, same single error message, same audit trail
 * as the website's sign-in.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string', 'max:100'],
        ]);

        $key = 'api-login:' . mb_strtolower($data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in ' . ceil(RateLimiter::availableIn($key) / 60) . ' minute(s).',
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 300);
            AuditLog::create([
                'action' => 'login_failed', 'detail' => 'App sign-in failed for ' . $data['email'],
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
            ]);
            throw ValidationException::withMessages(['email' => 'Those details do not match our records.']);
        }

        // Caregivers get their shifts; office staff get the office overview.
        // Families and clients use the website.
        $isCaregiver = $user->role === User::ROLE_CAREGIVER && $user->caregiver;
        if (! $isCaregiver && ! $user->isStaff()) {
            throw ValidationException::withMessages(['email' => 'This app is for caregivers and the office. Please use the website.']);
        }

        // The app does not do two-factor. While it is required for staff,
        // staff sign in on the website only, rather than the app quietly
        // skipping the second factor.
        if ($user->needsTwoFactor()) {
            throw ValidationException::withMessages(['email' => 'Office accounts need two-factor sign-in, which the app does not support yet. Please use the website.']);
        }

        if ($user->status === 'suspended') {
            throw ValidationException::withMessages(['email' => 'This account is suspended. Contact your coordinator.']);
        }

        RateLimiter::clear($key);
        $token = ApiToken::issue($user, $data['device'] ?? null);
        $user->forceFill(['last_login_at' => now()])->save();

        $request->setUserResolver(fn () => $user);
        AuditLog::record($request, 'login', 'user', $user->id, 'app' . (isset($data['device']) ? ': ' . $data['device'] : ''));

        return response()->json(['token' => $token] + $this->profile($user));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profile($request->user()));
    }

    /** First sign-in with a temporary password: choose your own. */
    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->status !== 'invited') {
            return response()->json(['message' => 'Your password is already set. Change it on the website.'], 409);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->uncompromised(),
                           fn ($attr, $value, $fail) => Hash::check($value, $user->password)
                               ? $fail('Choose a new password, not the temporary one.') : null],
        ], ['password.uncompromised' => 'That password appears in known data breaches. Choose another.']);

        $user->forceFill(['password' => Hash::make($request->string('password')->toString()), 'status' => 'active'])->save();

        // Any other phone that signed in with the temporary password is signed out.
        $current = $request->attributes->get('api_token');
        ApiToken::where('user_id', $user->id)->whereKeyNot($current?->id)->delete();

        AuditLog::record($request, 'password_chosen', 'user', $user->id, 'app');

        return response()->json($this->profile($user->fresh()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();
        AuditLog::record($request, 'logout', 'user', $request->user()->id, 'app');

        return response()->json(['message' => 'Signed out.']);
    }

    private function profile(User $user): array
    {
        return [
            'user' => [
                'name'   => $user->name,
                'email'  => $user->email,
                'code'   => $user->caregiver?->code,
                'status' => $user->status,
                'role'   => $user->role,
                'office' => $user->isStaff(),
            ],
            'password_change_required' => $user->status === 'invited',
        ];
    }
}
