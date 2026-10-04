<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Services\PlacementActions;
use App\Services\ShiftActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The office side of shifts: book them, move them, cancel them, cover
 * them. What happens during a shift is the caregiver's to record.
 */
class ShiftController extends Controller
{
    public function __construct(private ShiftActions $actions) {}

    /** Book a weekly pattern, e.g. Mon–Fri 08:00–13:00 for the next four weeks. */
    public function generate(Request $request, Assignment $assignment, PlacementActions $placements): RedirectResponse
    {
        return back()->with('status', $placements->generateShifts($request, $assignment));
    }

    /** One extra shift on one day. */
    public function store(Request $request, Assignment $assignment, PlacementActions $placements): RedirectResponse
    {
        $shift = $placements->addShift($request, $assignment);

        return back()->with('status', 'Shift booked for ' . $shift->shift_date->format('D j M') . '.');
    }

    public function show(Request $request, Shift $shift): View
    {
        $shift->load(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog']);
        AuditLog::record($request, 'viewed', 'shift', $shift->id, $shift->assignment?->patient?->code);

        return view('admin.shifts.show', [
            'shift'  => $shift,
            'relief' => $this->actions->reliefOptions($shift),
        ]);
    }

    /** Move a booked shift to another day or time. */
    public function update(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->move($request, $shift));
    }

    public function cancel(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->cancel($request, $shift));
    }

    /** Send a relief caregiver for this one shift, or take the cover off again. */
    public function cover(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->cover($request, $shift));
    }

    /** Correct a visit record (flat phone, forgotten check-out): reason required, audited. */
    public function correct(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->correct($request, $shift));
    }

    /** Nobody came. Recorded, not deleted, because that is what families ask about. */
    public function missed(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->missed($request, $shift));
    }
}
