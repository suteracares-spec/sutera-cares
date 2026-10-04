<?php

namespace App\Services;

use App\Http\Controllers\Admin\SignInController;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Family members' access to a client, and setting people up to sign in,
 * shared by the website and the app.
 */
class FamilyActions
{
    private const FLAGS = ['is_primary', 'is_bill_payer', 'can_view_notes', 'can_view_invoices', 'can_request_changes'];

    /**
     * Link a family member. One person may be responsible for more than one
     * client (two parents, say), so an existing family login is linked, not
     * duplicated. Any other kind of account is refused: the role decides
     * which product someone sees.
     *
     * @return array{0: Guardian, 1: string}
     */
    public function link(Request $request, Patient $patient): array
    {
        $data = $this->validated($request);
        $user = User::where('email', $data['email'])->first();

        if ($user && $user->role !== User::ROLE_GUARDIAN) {
            throw ValidationException::withMessages([
                'email' => 'That email belongs to a ' . $user->role . ' account. A family member needs their own email address.',
            ]);
        }
        if ($user && $patient->guardians()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['email' => "{$user->name} is already linked to this client."]);
        }

        $linkedExisting = (bool) $user;

        $guardian = DB::transaction(function () use ($patient, $data, $user) {
            if (! $user) {
                $user = User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'phone'    => $data['phone'] ?? null,
                    'password' => Hash::make(Str::password(16)),   // replaced when they set their own
                    'role'     => User::ROLE_GUARDIAN,
                    'status'   => 'invited',
                ]);
            } elseif ($user->status === 'suspended' && $user->guardianLinks()->doesntExist()) {
                // Suspended because their last link was removed; a new link
                // restores the access that removal took away.
                $user->update(['status' => $user->last_login_at ? 'active' : 'invited']);
            }

            if ($data['is_primary']) {
                $patient->guardians()->update(['is_primary' => false]);
            }

            return $patient->guardians()->create($this->linkFields($data) + ['user_id' => $user->id]);
        });

        $guardian->load('user');
        $this->audit('family_linked', $patient, $guardian, $request);

        return [$guardian, $linkedExisting
            ? "{$guardian->user->name} already had a family account, and is now linked to this client too."
            : "{$guardian->user->name} added. They are invited but cannot sign in until a password is set."];
    }

    public function update(Request $request, Guardian $guardian): string
    {
        $data = $this->validated($request, $guardian);
        $patient = $guardian->patient;

        DB::transaction(function () use ($guardian, $patient, $data) {
            // Name, email and phone belong to the person, so a change here
            // shows on every client they are linked to.
            $guardian->user->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);

            if ($data['is_primary']) {
                $patient->guardians()->whereKeyNot($guardian->id)->update(['is_primary' => false]);
            }
            $guardian->update($this->linkFields($data));
        });

        $this->audit('family_updated', $patient, $guardian, $request);

        return "{$guardian->user->name}'s access updated.";
    }

    /** Unlink. If that was their only client, their sign-in is suspended too. */
    public function unlink(Request $request, Guardian $guardian): string
    {
        $patient = $guardian->patient;
        $user = $guardian->user;

        $this->audit('family_unlinked', $patient, $guardian, $request);

        $suspended = DB::transaction(function () use ($guardian, $user) {
            $guardian->delete();
            if ($user->guardianLinks()->doesntExist()) {
                $user->update(['status' => 'suspended']);
                ApiToken::revokeAll($user);

                return true;
            }

            return false;
        });

        return "{$user->name} unlinked from this client"
            . ($suspended ? ' and their sign-in suspended.' : '. They keep access to the other clients they are linked to.');
    }

    /**
     * A temporary password, shown once, because the server sends no email.
     * The account goes back to "invited", so it is only good for choosing
     * their own. Coordinators set up clients, families and caregivers;
     * only an administrator resets a staff account; nobody resets their own.
     *
     * @return array{name: string, email: string, password: string, suspended: bool}
     */
    public function issueTemporaryPassword(Request $request, User $user): array
    {
        $actor = $request->user();
        abort_if($user->is($actor), 403, 'Change your own password on your account page.');
        abort_if($user->isStaff() && ! $actor->isAdmin(), 403, 'Only an administrator can reset a staff account.');

        $password = SignInController::generate();

        DB::transaction(function () use ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                // A suspended account stays suspended; the password is ready
                // for when access is restored.
                'status'   => $user->status === 'suspended' ? 'suspended' : 'invited',
            ])->save();

            // Anyone still signed in as them is signed out, phones included.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            ApiToken::revokeAll($user);
        });

        AuditLog::record($request, 'temp_password_issued', 'user', $user->id, $user->role);

        return ['name' => $user->name, 'email' => $user->email, 'password' => $password,
                'suspended' => $user->status === 'suspended'];
    }

    /**
     * A client's own sign-in to the small "my care" view.
     *
     * @return array{name: string, email: string, password: string, suspended: bool}
     */
    public function createClientLogin(Request $request, Patient $patient): array
    {
        if ($patient->user_id !== null) {
            throw ValidationException::withMessages(['email' => 'This client already has a sign-in.']);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
        ], ['email.unique' => 'That email already belongs to another account.']);

        $password = SignInController::generate();

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

        return ['name' => $user->name, 'email' => $user->email, 'password' => $password, 'suspended' => false];
    }

    private function validated(Request $request, ?Guardian $guardian = null): array
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'email'               => array_filter(['required', 'email', 'max:190',
                                       $guardian ? Rule::unique('users', 'email')->ignore($guardian->user_id) : null]),
            'phone'               => ['nullable', 'string', 'max:30'],
            'relationship'        => ['nullable', 'string', 'max:60'],
        ] + array_fill_keys(self::FLAGS, ['nullable', 'boolean']));

        foreach (self::FLAGS as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }

    private function linkFields(array $data): array
    {
        return ['relationship' => $data['relationship'] ?? null] + array_intersect_key($data, array_flip(self::FLAGS));
    }

    /** Logged against the client, so their record's history shows who could see it. */
    private function audit(string $action, Patient $patient, Guardian $guardian, Request $request): void
    {
        $access = collect(['can_view_notes' => 'notes', 'can_view_invoices' => 'invoices'])
            ->filter(fn ($label, $flag) => $guardian->{$flag})
            ->implode(', ');

        AuditLog::record($request, $action, 'patient', $patient->id,
            "{$patient->code}: family user #{$guardian->user_id}" . ($access ? ", sees {$access}" : ''));
    }
}
