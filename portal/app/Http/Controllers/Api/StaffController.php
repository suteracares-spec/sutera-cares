<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\StaffActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Office accounts and the audit log in the app, administrators only.
 * Account changes go through StaffActions, the same code the website uses.
 */
class StaffController extends Controller
{
    public function __construct(private StaffActions $actions) {}

    public function index(Request $request): JsonResponse
    {
        $staff = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_COORDINATOR])
            ->orderByRaw("status = 'suspended'")->orderBy('name')->get();

        return response()->json(['staff' => $staff->map(fn (User $u) => [
            'id'            => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'role'          => $u->role,
            'status'        => $u->status,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'two_factor'    => $u->two_factor_confirmed_at !== null,
            'is_me'         => $u->is($request->user()),
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        [$user, $password] = $this->actions->create($request);

        return response()->json([
            'message'     => "{$user->name} can now sign in. Give them the temporary password; they choose their own at first sign-in.",
            'credentials' => ['name' => $user->name, 'email' => $user->email, 'password' => $password, 'suspended' => false],
        ], 201);
    }

    public function status(Request $request, User $user): JsonResponse
    {
        return response()->json(['message' => $this->actions->toggleStatus($request, $user)]);
    }

    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        return response()->json(['message' => $this->actions->resetTwoFactor($request, $user)]);
    }

    /**
     * The audit log, newest first, 50 at a time: pass ?before=<id> of the
     * last entry for the next page. Reading it is not itself logged.
     */
    public function audit(Request $request): JsonResponse
    {
        $entries = AuditLog::query()->with('user')->filter($request)
            ->when($request->integer('before'), fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')->limit(50)->get();

        return response()->json([
            'entries' => $entries->map(fn (AuditLog $e) => [
                'id'      => $e->id,
                'at'      => $e->created_at?->toIso8601String(),
                'user'    => $e->user?->name,
                'action'  => $e->action,
                'subject' => $e->subject_type ? $e->subject_type . ($e->subject_id ? ":{$e->subject_id}" : '') : null,
                'detail'  => $e->detail,
                'ip'      => $e->ip_address,
            ])->values(),
            'more'    => $entries->count() === 50,
            'users'   => User::whereIn('id', AuditLog::query()->select('user_id')->distinct())->orderBy('name')
                            ->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
