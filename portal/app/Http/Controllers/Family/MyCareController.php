<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The person receiving care, if they sign in at all. Deliberately small:
 * who is coming and when, and a way to say something is wrong. Many
 * clients never log in, and that is fine; their family is the main user.
 */
class MyCareController extends Controller
{
    public function show(Request $request): View
    {
        $patient = $request->user()->patientRecord ?? abort(403, 'No client record is linked to this account.');

        return view('family.my-care', [
            'patient' => $patient,
            'coming'  => Shift::query()
                ->whereHas('assignment', fn ($q) => $q->where('patient_id', $patient->id))
                ->with(['assignment.caregiver.user', 'assignment.service', 'coveredBy.user'])
                ->whereDate('shift_date', '>=', today())->whereDate('shift_date', '<=', today()->addDays(7))
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->orderBy('shift_date')->orderBy('start_time')->get(),
        ]);
    }

    public function concern(Request $request): RedirectResponse
    {
        $patient = $request->user()->patientRecord ?? abort(403);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Concern::CATEGORIES))],
            'detail'   => ['required', 'string', 'max:2000'],
        ]);

        $concern = Concern::create($data + [
            'raised_by_id' => $request->user()->id,
            'patient_id'   => $patient->id,
            'status'       => 'open',
        ]);

        AuditLog::record($request, 'concern_raised', 'concern', $concern->id, "{$patient->code} by client");

        return back()->with('status', 'Thank you. The office has your message and will be in touch.');
    }
}
