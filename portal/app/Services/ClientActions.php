<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\CarePlanTask;
use App\Models\Patient;
use App\Rules\NonClinicalTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Client records and their care plans, shared by the website and the app
 * so both apply the same validation, the same rules (no clinical tasks;
 * no activation without consent; an agreed plan is never edited) and the
 * same audit trail. Each method validates its own input from the request.
 */
class ClientActions
{
    public const STATUSES = ['enquiry', 'assessment', 'active', 'paused', 'closed'];

    public const MOBILITY = ['independent', 'walks_with_aid', 'wheelchair', 'bed_bound'];

    // ---- Client records ---------------------------------------------------

    public function create(Request $request): Patient
    {
        $patient = Patient::create($this->validatedClient($request) + ['code' => Patient::nextCode()]);
        $this->auditClient('created', $patient, $request);

        return $patient;
    }

    public function update(Request $request, Patient $patient): string
    {
        $patient->update($this->validatedClient($request));
        $this->auditClient('updated', $patient, $request);

        return 'Client record updated.';
    }

    /**
     * Soft delete. A client record is evidence for as long as the retention
     * policy says, and you cannot delete your way out of a dispute.
     */
    public function archive(Request $request, Patient $patient): string
    {
        $patient->delete();
        $this->auditClient('deleted', $patient, $request);

        return "Client {$patient->code} archived.";
    }

    private function validatedClient(Request $request): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:150'],
            'ic_number'        => ['nullable', 'string', 'max:20'],
            'dob'              => ['nullable', 'date', 'before:today'],
            'gender'           => ['nullable', 'in:female,male,other'],
            'address'          => ['nullable', 'string', 'max:500'],
            'area'             => ['nullable', 'string', 'max:120'],
            'postcode'         => ['nullable', 'string', 'max:10'],
            'mobility_level'   => ['nullable', Rule::in(self::MOBILITY)],
            'languages'        => ['nullable', 'string', 'max:200'],
            'allergies'        => ['nullable', 'string', 'max:2000'],
            'notes'            => ['nullable', 'string', 'max:5000'],
            'consent_given_at' => ['nullable', 'date'],
            'consent_by'       => ['nullable', 'string', 'max:150'],
            'status'           => ['required', Rule::in(self::STATUSES)],
        ]);
    }

    private function auditClient(string $action, Patient $patient, Request $request): void
    {
        AuditLog::record($request, $action, 'patient', $patient->id, $patient->code);
    }

    // ---- Care plans -------------------------------------------------------

    /**
     * Start a new version. One draft at a time per client: if there is
     * already one, carry on with it. A revision starts as a copy of the
     * current plan, because most changes are small and retyping the rest
     * invites mistakes.
     *
     * @return array{0: CarePlan, 1: string}
     */
    public function startPlan(Request $request, Patient $patient): array
    {
        if ($draft = $patient->carePlans()->where('status', 'draft')->first()) {
            return [$draft, "Version {$draft->version} is already in draft. Continue it here."];
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

        $this->auditPlan('created', $plan, $request);

        return [$plan, $basis
            ? "Version {$plan->version} started as a copy of version {$basis->version}. Change what has changed."
            : 'First care plan started. Add what this person needs help with.'];
    }

    /** Save a draft's date, notes and tasks. Clinical tasks are refused. */
    public function saveDraft(Request $request, CarePlan $plan): string
    {
        $this->refuseUnlessDraft($plan);

        $data = $request->validate([
            'effective_from'      => ['required', 'date'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'tasks'               => ['nullable', 'array', 'max:60'],
            'tasks.*.description' => ['nullable', 'string', 'max:400', new NonClinicalTask],
            'tasks.*.category'    => ['required_with:tasks.*.description', 'nullable', Rule::in(array_keys(CarePlanTask::CATEGORIES))],
            'tasks.*.frequency'   => ['nullable', Rule::in(array_keys(CarePlanTask::FREQUENCIES))],
            'tasks.*.time_of_day' => ['nullable', Rule::in(array_keys(CarePlanTask::TIMES))],
        ], [
            'tasks.*.category.required_with' => 'Every task needs a category.',
        ]);

        // Blank rows are the spare rows on the form, not tasks.
        $tasks = collect($data['tasks'] ?? [])
            ->filter(fn ($t) => trim((string) ($t['description'] ?? '')) !== '')
            ->values();

        DB::transaction(function () use ($plan, $data, $tasks) {
            $plan->update(['effective_from' => $data['effective_from'], 'notes' => $data['notes'] ?? null]);

            // Replacing a draft's rows is safe: nothing references them
            // until the plan is activated.
            $plan->tasks()->delete();
            foreach ($tasks as $i => $t) {
                $plan->tasks()->create([
                    'category'    => $t['category'],
                    'description' => trim($t['description']),
                    'frequency'   => $t['frequency'] ?? 'every_visit',
                    'time_of_day' => $t['time_of_day'] ?? 'any',
                    'sort_order'  => $i,
                ]);
            }
        });

        $this->auditPlan('updated', $plan, $request);

        return 'Draft saved.';
    }

    /**
     * Make a draft the plan care is delivered against. It needs someone to
     * have agreed it, at least one task, and the client's recorded consent,
     * because this is the moment care is about to begin.
     */
    public function activatePlan(Request $request, CarePlan $plan): string
    {
        $this->refuseUnlessDraft($plan);

        $data = $request->validate([
            'agreed_by' => ['required', 'string', 'max:150'],
            'agreed_at' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'agreed_by.required' => 'Record who agreed this plan: the client, or the family member who did.',
        ]);

        $patient = $plan->patient;
        $current = $patient->activeCarePlan();

        $problem = match (true) {
            ! $patient->hasConsent()        => 'This client has no recorded consent. Record it on the client record before activating a care plan.',
            $plan->tasks()->doesntExist()   => 'A care plan with no tasks cannot be activated.',
            $current && $plan->effective_from->lte($current->effective_from)
                => "This version must take effect after version {$current->version}, which started "
                   . $current->effective_from->format('j M Y') . '.',
            default => null,
        };
        if ($problem) {
            throw ValidationException::withMessages(['agreed_by' => $problem]);
        }

        DB::transaction(function () use ($plan, $current, $data) {
            $current?->update(['status' => 'superseded', 'effective_to' => $plan->effective_from->copy()->subDay()]);
            $plan->update(['status' => 'active', 'agreed_by' => $data['agreed_by'], 'agreed_at' => Carbon::parse($data['agreed_at'])]);
        });

        $this->auditPlan('activated', $plan, $request);

        return "Version {$plan->version} is now the active care plan"
            . ($current ? "; version {$current->version} is kept as history." : '.');
    }

    /** Only a draft can be thrown away. Agreed plans are history. */
    public function discardDraft(Request $request, CarePlan $plan): string
    {
        $this->refuseUnlessDraft($plan);

        $this->auditPlan('deleted', $plan, $request);
        $plan->delete();

        return "Draft version {$plan->version} discarded.";
    }

    public function refuseUnlessDraft(CarePlan $plan): void
    {
        if (! $plan->isDraft()) {
            throw ValidationException::withMessages([
                'effective_from' => "Version {$plan->version} has been agreed and can no longer be changed. Start a revision instead.",
            ]);
        }
    }

    private function auditPlan(string $action, CarePlan $plan, Request $request): void
    {
        AuditLog::record($request, $action, 'care_plan', $plan->id,
            ($plan->patient?->code ?? 'patient #' . $plan->patient_id) . ' v' . $plan->version);
    }
}
