<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\CarePlanTask;
use App\Models\Patient;
use App\Rules\NonClinicalTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $draft = $patient->carePlans()->where('status', 'draft')->first();

        if ($draft) {
            return redirect()->route('admin.care-plans.edit', $draft)
                ->with('status', "Version {$draft->version} is already in draft. Continue it here.");
        }

        $basis = $patient->activeCarePlan();

        $plan = DB::transaction(function () use ($patient, $basis, $request) {
            $plan = $patient->carePlans()->create([
                'version'        => ($patient->carePlans()->max('version') ?? 0) + 1,
                'effective_from' => today(),
                'author_user_id' => $request->user()->id,
                'notes'          => $basis?->notes,
                'status'         => 'draft',
            ]);

            foreach ($basis?->tasks ?? [] as $task) {
                $plan->tasks()->create($task->only(['category', 'description', 'frequency', 'time_of_day', 'sort_order']));
            }

            return $plan;
        });

        $this->audit('created', $plan, $request);

        return redirect()->route('admin.care-plans.edit', $plan)
            ->with('status', $basis
                ? "Version {$plan->version} started as a copy of version {$basis->version}. Change what has changed."
                : 'First care plan started. Add what this person needs help with.');
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

    public function update(Request $request, CarePlan $carePlan): RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        $data = $request->validate([
            'effective_from'        => ['required', 'date'],
            'notes'                 => ['nullable', 'string', 'max:5000'],
            'tasks'                 => ['nullable', 'array', 'max:60'],
            'tasks.*.description'   => ['nullable', 'string', 'max:400', new NonClinicalTask],
            'tasks.*.category'      => ['required_with:tasks.*.description', 'nullable', Rule::in(array_keys(CarePlanTask::CATEGORIES))],
            'tasks.*.frequency'     => ['nullable', Rule::in(array_keys(CarePlanTask::FREQUENCIES))],
            'tasks.*.time_of_day'   => ['nullable', Rule::in(array_keys(CarePlanTask::TIMES))],
        ], [
            'tasks.*.category.required_with' => 'Every task needs a category.',
        ]);

        // Blank rows are the spare rows on the form, not tasks.
        $tasks = collect($data['tasks'] ?? [])
            ->filter(fn ($t) => trim((string) ($t['description'] ?? '')) !== '')
            ->values();

        DB::transaction(function () use ($carePlan, $data, $tasks) {
            $carePlan->update([
                'effective_from' => $data['effective_from'],
                'notes'          => $data['notes'] ?? null,
            ]);

            // Replacing the rows of a draft is safe: nothing references a
            // draft's tasks until it is activated.
            $carePlan->tasks()->delete();

            foreach ($tasks as $i => $t) {
                $carePlan->tasks()->create([
                    'category'    => $t['category'],
                    'description' => trim($t['description']),
                    'frequency'   => $t['frequency'] ?? 'every_visit',
                    'time_of_day' => $t['time_of_day'] ?? 'any',
                    'sort_order'  => $i,
                ]);
            }
        });

        $this->audit('updated', $carePlan, $request);

        if ($request->boolean('done')) {
            return redirect()->route('admin.care-plans.show', $carePlan)
                ->with('status', 'Draft saved. Activate it once it has been agreed with the client or family.');
        }

        return redirect()->route('admin.care-plans.edit', $carePlan)->with('status', 'Draft saved.');
    }

    /**
     * Make this draft the plan care is delivered against. It needs someone
     * to have agreed it, at least one task, and the client's recorded
     * consent, because this is the moment care is about to begin.
     */
    public function activate(Request $request, CarePlan $carePlan): RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        $data = $request->validate([
            'agreed_by' => ['required', 'string', 'max:150'],
            'agreed_at' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'agreed_by.required' => 'Record who agreed this plan: the client, or the family member who did.',
        ]);

        $patient = $carePlan->patient;
        $current = $patient->activeCarePlan();

        $problems = [];
        if (! $patient->hasConsent()) {
            $problems['agreed_by'] = 'This client has no recorded consent. Record it on the client record before activating a care plan.';
        } elseif ($carePlan->tasks()->doesntExist()) {
            $problems['agreed_by'] = 'A care plan with no tasks cannot be activated.';
        } elseif ($current && $carePlan->effective_from->lte($current->effective_from)) {
            $problems['agreed_by'] = "This version must take effect after version {$current->version}, which started "
                . $current->effective_from->format('j M Y') . '.';
        }

        if ($problems) {
            throw ValidationException::withMessages($problems);
        }

        DB::transaction(function () use ($carePlan, $current, $data) {
            $current?->update([
                'status'       => 'superseded',
                'effective_to' => $carePlan->effective_from->copy()->subDay(),
            ]);

            $carePlan->update([
                'status'    => 'active',
                'agreed_by' => $data['agreed_by'],
                'agreed_at' => Carbon::parse($data['agreed_at']),
            ]);
        });

        $this->audit('activated', $carePlan, $request);

        return redirect()->route('admin.care-plans.show', $carePlan)
            ->with('status', "Version {$carePlan->version} is now the active care plan"
                . ($current ? "; version {$current->version} is kept as history." : '.'));
    }

    /** Only a draft can be thrown away. Agreed plans are history. */
    public function destroy(Request $request, CarePlan $carePlan): RedirectResponse
    {
        if ($redirect = $this->refuseUnlessDraft($carePlan)) {
            return $redirect;
        }

        $patient = $carePlan->patient;
        $this->audit('deleted', $carePlan, $request);
        $carePlan->delete();

        return redirect()->route('admin.patients.show', $patient)
            ->with('status', "Draft version {$carePlan->version} discarded.");
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
