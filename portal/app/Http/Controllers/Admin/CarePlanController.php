<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Services\ClientActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Care plans are versioned, never edited in place once agreed. A change
 * of need is a new version: draft it, agree it with the family, activate
 * it, and the old one is kept, superseded, with the date it stopped.
 */
class CarePlanController extends Controller
{
    /**
     * Start a new version. One draft at a time per client: if there is
     * already one, carry on with it rather than starting a second. A
     * revision starts as a copy of the current plan, because most changes
     * are small and retyping the rest invites mistakes.
     */
    public function store(Request $request, Patient $patient, ClientActions $actions): RedirectResponse
    {
        [$plan, $message] = $actions->startPlan($request, $patient);

        return redirect()->route('admin.care-plans.edit', $plan)->with('status', $message);
    }

    public function show(Request $request, CarePlan $carePlan): View
    {
        $this->audit('viewed', $carePlan, $request);

        $carePlan->load(['patient', 'tasks', 'author']);
        $history = $carePlan->patient->carePlans()->orderByDesc('version')->get();

        return view('admin.care-plans.show', ['plan' => $carePlan, 'history' => $history]);
    }

    public function edit(CarePlan $carePlan): View|RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        $carePlan->load(['patient', 'tasks']);

        return view('admin.care-plans.form', ['plan' => $carePlan]);
    }

    public function update(Request $request, CarePlan $carePlan, ClientActions $actions): RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        $actions->saveDraft($request, $carePlan);

        return $request->boolean('done')
            ? redirect()->route('admin.care-plans.show', $carePlan)
                ->with('status', 'Draft saved. Activate it once it has been agreed with the client or family.')
            : redirect()->route('admin.care-plans.edit', $carePlan)->with('status', 'Draft saved.');
    }

    /**
     * Make this draft the plan care is delivered against. It needs someone
     * to have agreed it, at least one task, and the client's recorded
     * consent, because this is the moment care is about to begin.
     */
    public function activate(Request $request, CarePlan $carePlan, ClientActions $actions): RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        return redirect()->route('admin.care-plans.show', $carePlan)
            ->with('status', $actions->activatePlan($request, $carePlan));
    }

    /** Only a draft can be thrown away. Agreed plans are history. */
    public function destroy(Request $request, CarePlan $carePlan, ClientActions $actions): RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        $patient = $carePlan->patient;

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', $actions->discardDraft($request, $carePlan));
    }

    private function refuseUnlessDraft(CarePlan $plan): ?RedirectResponse
    {
        if ($plan->isDraft()) {
            return null;
        }

        return redirect()->route('admin.care-plans.show', $plan)
            ->with('status', "Version {$plan->version} has been agreed and can no longer be changed. Start a revision instead.");
    }

    private function audit(string $action, CarePlan $plan, Request $request): void
    {
        AuditLog::create([
            'user_id'      => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'care_plan',
            'subject_id'   => $plan->id,
            'detail'       => ($plan->patient?->code ?? 'patient #' . $plan->patient_id) . ' v' . $plan->version,
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 300),
        ]);
    }
}
