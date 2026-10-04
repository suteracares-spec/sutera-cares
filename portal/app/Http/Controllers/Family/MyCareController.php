<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Services\FamilyView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The person receiving care, if they sign in at all. Deliberately small:
 * who is coming and when, and a way to say something is wrong. Many
 * clients never log in, and that is fine; their family is the main user.
 */
class MyCareController extends Controller
{
    public function __construct(private FamilyView $family) {}

    public function show(Request $request): View
    {
        $patient = $request->user()->patientRecord ?? abort(403, 'No client record is linked to this account.');

        return view('family.my-care', [
            'patient' => $patient,
            'coming'  => $this->family->coming($patient),
        ]);
    }

    public function concern(Request $request): RedirectResponse
    {
        $patient = $request->user()->patientRecord ?? abort(403);

        $this->family->raiseConcern($request, $patient, false, 'client');

        return back()->with('status', 'Thank you. The office has your message and will be in touch.');
    }
}
