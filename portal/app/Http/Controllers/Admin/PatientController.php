<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $patients = Patient::query()
            ->when($request->string('q')->toString(), fn ($query, $term) =>
                $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                                            ->orWhere('code', 'like', "%{$term}%")
                                            ->orWhere('area', 'like', "%{$term}%")))
            ->when($request->string('status')->toString(), fn ($query, $status) =>
                $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.patients.index', compact('patients'));
    }

    public function create(): View
    {
        return view('admin.patients.form', [
            'patient' => new Patient(['code' => Patient::nextCode(), 'status' => 'enquiry']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = Patient::create($this->validated($request) + ['code' => Patient::nextCode()]);

        $this->audit('created', $patient, $request);

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', "Client {$patient->code} created.");
    }

    public function show(Request $request, Patient $patient): View
    {
        // PDPA: every read of a client record is logged, not just writes.
        $this->audit('viewed', $patient, $request);

        $patient->load(['guardians.user', 'assignments.caregiver.user']);

        return view('admin.patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        return view('admin.patients.form', compact('patient'));
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update($this->validated($request));

        $this->audit('updated', $patient, $request);

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', 'Client record updated.');
    }

    public function destroy(Request $request, Patient $patient): RedirectResponse
    {
        // Soft delete. A client record is evidence for as long as the
        // retention policy says, and you cannot delete your way out of a
        // dispute.
        $patient->delete();

        $this->audit('deleted', $patient, $request);

        return redirect()->route('admin.patients.index')
            ->with('status', "Client {$patient->code} archived.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:150'],
            'ic_number'        => ['nullable', 'string', 'max:20'],
            'dob'              => ['nullable', 'date', 'before:today'],
            'gender'           => ['nullable', 'in:female,male,other'],
            'address'          => ['nullable', 'string', 'max:500'],
            'area'             => ['nullable', 'string', 'max:120'],
            'postcode'         => ['nullable', 'string', 'max:10'],
            'mobility_level'   => ['nullable', 'in:independent,walks_with_aid,wheelchair,bed_bound'],
            'languages'        => ['nullable', 'string', 'max:200'],
            'allergies'        => ['nullable', 'string', 'max:2000'],
            'notes'            => ['nullable', 'string', 'max:5000'],
            'consent_given_at' => ['nullable', 'date'],
            'consent_by'       => ['nullable', 'string', 'max:150'],
            'status'           => ['required', 'in:enquiry,assessment,active,paused,closed'],
        ]);
    }

    private function audit(string $action, Patient $patient, Request $request): void
    {
        AuditLog::create([
            'user_id'      => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'patient',
            'subject_id'   => $patient->id,
            'detail'       => $patient->code,
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 300),
        ]);
    }
}
