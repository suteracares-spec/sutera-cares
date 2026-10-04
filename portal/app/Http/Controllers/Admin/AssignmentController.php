<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Patient;
use App\Models\Service;
use App\Services\PlacementActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placing a caregiver with a client: an ongoing assignment, or a one-off
 * visit such as a massage, which is the same thing on a single day.
 */
class AssignmentController extends Controller
{
    public function create(Request $request, Patient $patient): View
    {
        $oneOff = $request->query('type') === 'visit';

        return view('admin.assignments.form', [
            'patient'    => $patient,
            'oneOff'     => $oneOff,
            'caregivers' => $this->placeable(),
            'services'   => Service::where('active', true)
                ->when($oneOff, fn ($q) => $q->orderByRaw("category = 'wellness' desc"))
                ->orderBy('name')->get(),
            'activePlan' => $patient->activeCarePlan(),
        ]);
    }

    public function store(Request $request, Patient $patient, PlacementActions $actions): RedirectResponse
    {
        [$assignment, $message] = $actions->createAssignment($request, $patient);

        return redirect()->route('admin.assignments.show', $assignment)->with('status', $message);
    }

    public function show(Assignment $assignment): View
    {
        $assignment->load(['patient', 'caregiver.user', 'service', 'carePlan']);

        $upcoming = $assignment->shifts()->with(['coveredBy.user', 'visitLog'])
            ->whereDate('shift_date', '>=', today())->orderBy('shift_date')->orderBy('start_time')->limit(60)->get();
        $recent = $assignment->shifts()->with(['coveredBy.user', 'visitLog'])
            ->whereDate('shift_date', '<', today())->orderByDesc('shift_date')->orderByDesc('start_time')->limit(20)->get();

        return view('admin.assignments.show', [
            'assignment' => $assignment,
            'upcoming'   => $upcoming,
            'recent'     => $recent,
            'relief'     => $this->placeable()->reject(fn ($c) => $c->id === $assignment->caregiver_id),
        ]);
    }

    /** Confirm a proposed placement, or change the agreed rate and notes. */
    public function update(Request $request, Assignment $assignment, PlacementActions $actions): RedirectResponse
    {
        return back()->with('status', $actions->updateAssignment($request, $assignment));
    }

    /** End it: set the last day and cancel anything booked after it. */
    public function end(Request $request, Assignment $assignment, PlacementActions $actions): RedirectResponse
    {
        return back()->with('status', $actions->endAssignment($request, $assignment));
    }

    /** Caregivers who can be sent today, by name. */
    private function placeable()
    {
        return app(PlacementActions::class)->placeable();
    }
}
