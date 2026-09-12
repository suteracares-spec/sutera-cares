<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaregiverController extends Controller
{
    public function index(Request $request): View
    {
        $caregivers = Caregiver::query()
            ->with('user')
            ->when($request->string('q')->toString(), fn ($query, $term) =>
                $query->where(fn ($q) => $q->where('code', 'like', "%{$term}%")
                    ->orWhere('base_area', 'like', "%{$term}%")
                    ->orWhere('skills', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))))
            ->when($request->string('status')->toString(), fn ($query, $status) =>
                $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.caregivers.index', compact('caregivers'));
    }

    public function create(): View
    {
        return view('admin.caregivers.form', [
            'caregiver' => new Caregiver(['code' => Caregiver::nextCode(), 'status' => 'applicant']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // A caregiver is a person who signs in, so the login account and the
        // staff record are created together or not at all.
        $caregiver = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make(Str::password(16)),   // replaced when they set their own
                'role'     => User::ROLE_CAREGIVER,
                'status'   => 'invited',
            ]);

            return Caregiver::create($this->staffFields($data) + [
                'user_id' => $user->id,
                'code'    => Caregiver::nextCode(),
            ]);
        });

        $this->audit('created', $caregiver, $request);

        return redirect()->route('admin.caregivers.show', $caregiver)
            ->with('status', "Caregiver {$caregiver->code} created. They are invited but cannot sign in until a password is set.");
    }

    public function show(Request $request, Caregiver $caregiver): View
    {
        $this->audit('viewed', $caregiver, $request);

        $caregiver->load(['user', 'assignments.patient']);

        return view('admin.caregivers.show', compact('caregiver'));
    }

    public function edit(Caregiver $caregiver): View
    {
        $caregiver->load('user');

        return view('admin.caregivers.form', compact('caregiver'));
    }

    public function update(Request $request, Caregiver $caregiver): RedirectResponse
    {
        $data = $this->validated($request, $caregiver);

        DB::transaction(function () use ($caregiver, $data) {
            $caregiver->user->update([
                'name'  => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                // Leaving keeps the record but revokes the building.
                'status' => in_array($data['status'], ['left', 'inactive'], true) ? 'suspended' : 'active',
            ]);

            $caregiver->update($this->staffFields($data));
        });

        $this->audit('updated', $caregiver, $request);

        return redirect()->route('admin.caregivers.show', $caregiver)
            ->with('status', 'Caregiver record updated.');
    }

    public function destroy(Request $request, Caregiver $caregiver): RedirectResponse
    {
        DB::transaction(function () use ($caregiver) {
            $caregiver->user->update(['status' => 'suspended']);
            $caregiver->update(['status' => 'left']);
            $caregiver->delete();   // soft — past visit records stay attached
        });

        $this->audit('archived', $caregiver, $request);

        return redirect()->route('admin.caregivers.index')
            ->with('status', "Caregiver {$caregiver->code} archived and access revoked.");
    }

    private function validated(Request $request, ?Caregiver $caregiver = null): array
    {
        return $request->validate([
            'name'                    => ['required', 'string', 'max:150'],
            'email'                   => ['required', 'email', 'max:190',
                                          Rule::unique('users', 'email')->ignore($caregiver?->user_id)],
            'phone'                   => ['nullable', 'string', 'max:30'],
            'ic_number'               => ['nullable', 'string', 'max:20'],
            'gender'                  => ['nullable', 'in:female,male,other'],
            'dob'                     => ['nullable', 'date', 'before:today'],
            'languages'               => ['nullable', 'string', 'max:200'],
            'skills'                  => ['nullable', 'string', 'max:500'],
            'base_area'               => ['nullable', 'string', 'max:120'],
            'has_own_transport'       => ['nullable', 'boolean'],
            'max_travel_km'           => ['nullable', 'integer', 'min:0', 'max:500'],
            'hourly_rate'             => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'police_check_expires_at' => ['nullable', 'date'],
            'right_to_work_verified'  => ['nullable', 'boolean'],
            'status'                  => ['required', 'in:applicant,vetting,active,inactive,left'],
        ]);
    }

    /** The fields that belong on the staff record rather than the login. */
    private function staffFields(array $data): array
    {
        return [
            'ic_number'               => $data['ic_number'] ?? null,
            'gender'                  => $data['gender'] ?? null,
            'dob'                     => $data['dob'] ?? null,
            'languages'               => $data['languages'] ?? null,
            'skills'                  => $data['skills'] ?? null,
            'base_area'               => $data['base_area'] ?? null,
            'has_own_transport'       => (bool) ($data['has_own_transport'] ?? false),
            'max_travel_km'           => $data['max_travel_km'] ?? null,
            'hourly_rate'             => $data['hourly_rate'] ?? null,
            'police_check_expires_at' => $data['police_check_expires_at'] ?? null,
            'right_to_work_verified'  => (bool) ($data['right_to_work_verified'] ?? false),
            'status'                  => $data['status'],
        ];
    }

    private function audit(string $action, Caregiver $caregiver, Request $request): void
    {
        AuditLog::create([
            'user_id'      => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'caregiver',
            'subject_id'   => $caregiver->id,
            'detail'       => $caregiver->code,
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 300),
        ]);
    }
}
