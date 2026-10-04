<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Services\PlacementActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, PlacementActions $actions): RedirectResponse
    {
        $caregiver = $actions->createCaregiver($request);

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

    public function update(Request $request, Caregiver $caregiver, PlacementActions $actions): RedirectResponse
    {
        return redirect()->route('admin.caregivers.show', $caregiver)
            ->with('status', $actions->updateCaregiver($request, $caregiver));
    }

    public function destroy(Request $request, Caregiver $caregiver, PlacementActions $actions): RedirectResponse
    {
        return redirect()->route('admin.caregivers.index')
            ->with('status', $actions->archiveCaregiver($request, $caregiver));
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
