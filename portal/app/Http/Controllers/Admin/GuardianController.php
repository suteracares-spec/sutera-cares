<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Patient;
use App\Services\FamilyActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, Patient $patient, FamilyActions $actions): RedirectResponse
    {
        [, $message] = $actions->link($request, $patient);

        return redirect()->route('admin.patients.show', $patient)->with('status', $message);
    }

    public function edit(Guardian $guardian): View
    {
        $guardian->load(['user', 'patient']);

        return view('admin.guardians.form', ['patient' => $guardian->patient, 'guardian' => $guardian]);
    }

    public function update(Request $request, Guardian $guardian, FamilyActions $actions): RedirectResponse
    {
        return redirect()->route('admin.patients.show', $guardian->patient)
            ->with('status', $actions->update($request, $guardian));
    }

    /**
     * Unlink. If that was their only client, their sign-in is suspended as
     * well; a family login with nobody to look at should not stay open.
     */
    public function destroy(Request $request, Guardian $guardian, FamilyActions $actions): RedirectResponse
    {
        $patient = $guardian->patient;

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', $actions->unlink($request, $guardian));
    }
}
