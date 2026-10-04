<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Family members linked to a client. The family member is a person with a
 * login; the link carries what they may see, because that differs from
 * one relative to the next and from one client to the next.
 */
class GuardianController extends Controller
{
    public function create(Patient $patient): View
    {
        return view('admin.guardians.form', [
            'patient'  => $patient,
            // Notes are off until someone decides otherwise. An estranged
            // relative should not read personal-care notes by default.
            'guardian' => new Guardian([
                'is_primary'          => $patient->guardians()->doesntExist(),
                'can_view_notes'      => false,
                'can_view_invoices'   => false,
                'can_request_changes' => true,
            ]),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $this->validated($request);

        // One person may be responsible for more than one client — two
        // parents, say — so an existing family login is linked, not
        // duplicated. Any other kind of account is refused: a caregiver
        // or a member of staff signing in as family would see the wrong
        // product, and the role decides everything.
        $user = User::where('email', $data['email'])->first();

        if ($user && $user->role !== User::ROLE_GUARDIAN) {
            throw ValidationException::withMessages([
                'email' => 'That email belongs to a ' . $user->role . ' account. A family member needs their own email address.',
            ]);
        }

        if ($user && $patient->guardians()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$user->name} is already linked to this client.",
            ]);
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

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', $linkedExisting
                ? "{$guardian->user->name} already had a family account, and is now linked to this client too."
                : "{$guardian->user->name} added. They are invited but cannot sign in until a password is set.");
    }

    public function edit(Guardian $guardian): View
    {
        $guardian->load(['user', 'patient']);

        return view('admin.guardians.form', ['patient' => $guardian->patient, 'guardian' => $guardian]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $this->validated($request, $guardian);
        $patient = $guardian->patient;

        DB::transaction(function () use ($guardian, $patient, $data) {
            // Name, email and phone belong to the person, so a change here
            // shows on every client they are linked to.
            $guardian->user->update([
                'name'  => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            if ($data['is_primary']) {
                $patient->guardians()->whereKeyNot($guardian->id)->update(['is_primary' => false]);
            }

            $guardian->update($this->linkFields($data));
        });

        $this->audit('family_updated', $patient, $guardian, $request);

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', "{$guardian->user->name}'s access updated.");
    }

    /**
     * Unlink. If that was their only client, their sign-in is suspended as
     * well; a family login with nobody to look at should not stay open.
     */
    public function destroy(Request $request, Guardian $guardian): RedirectResponse
    {
        $patient = $guardian->patient;
        $user = $guardian->user;

        $this->audit('family_unlinked', $patient, $guardian, $request);

        $suspended = DB::transaction(function () use ($guardian, $user) {
            $guardian->delete();

            if ($user->guardianLinks()->doesntExist()) {
                $user->update(['status' => 'suspended']);

                return true;
            }

            return false;
        });

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', "{$user->name} unlinked from this client"
                . ($suspended ? ' and their sign-in suspended.' : '. They keep access to the other clients they are linked to.'));
    }

    private function validated(Request $request, ?Guardian $guardian = null): array
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'email'               => array_filter(['required', 'email', 'max:190',
                                       $guardian ? Rule::unique('users', 'email')->ignore($guardian->user_id) : null]),
            'phone'               => ['nullable', 'string', 'max:30'],
            'relationship'        => ['nullable', 'string', 'max:60'],
            'is_primary'          => ['nullable', 'boolean'],
            'is_bill_payer'       => ['nullable', 'boolean'],
            'can_view_notes'      => ['nullable', 'boolean'],
            'can_view_invoices'   => ['nullable', 'boolean'],
            'can_request_changes' => ['nullable', 'boolean'],
        ]);

        foreach (['is_primary', 'is_bill_payer', 'can_view_notes', 'can_view_invoices', 'can_request_changes'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }

    /** The fields that belong on the link rather than the person. */
    private function linkFields(array $data): array
    {
        return [
            'relationship'        => $data['relationship'] ?? null,
            'is_primary'          => $data['is_primary'],
            'is_bill_payer'       => $data['is_bill_payer'],
            'can_view_notes'      => $data['can_view_notes'],
            'can_view_invoices'   => $data['can_view_invoices'],
            'can_request_changes' => $data['can_request_changes'],
        ];
    }

    /** Logged against the client, so their record's history shows who could see it. */
    private function audit(string $action, Patient $patient, Guardian $guardian, Request $request): void
    {
        $access = collect(['can_view_notes' => 'notes', 'can_view_invoices' => 'invoices'])
            ->filter(fn ($label, $flag) => $guardian->{$flag})
            ->implode(', ');

        AuditLog::create([
            'user_id'      => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'patient',
            'subject_id'   => $patient->id,
            'detail'       => mb_substr("{$patient->code}: family user #{$guardian->user_id}"
                              . ($access ? ", sees {$access}" : ''), 0, 500),
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 300),
        ]);
    }
}
