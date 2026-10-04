<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\ClientActions;
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

    public function store(Request $request, ClientActions $actions): RedirectResponse
    {
        $patient = $actions->create($request);

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', "Client {$patient->code} created.");
    }

    public function show(Request $request, Patient $patient): View
    {
        // PDPA: every read of a client record is logged, not just writes.
        $this->audit('viewed', $patient, $request);

        $patient->load([
            'guardians' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'guardians.user',
            'assignments.caregiver.user',
            'assignments.service',
            'carePlans' => fn ($q) => $q->withCount('tasks')->orderByDesc('version'),
        ]);

        return view('admin.patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        return view('admin.patients.form', compact('patient'));
    }

    public function update(Request $request, Patient $patient, ClientActions $actions): RedirectResponse
    {
        return redirect()->route('admin.patients.show', $patient)
            ->with('status', $actions->update($request, $patient));
    }

    public function destroy(Request $request, Patient $patient, ClientActions $actions): RedirectResponse
    {
        return redirect()->route('admin.patients.index')
            ->with('status', $actions->archive($request, $patient));
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
