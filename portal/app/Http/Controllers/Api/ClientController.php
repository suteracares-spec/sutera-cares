<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\CarePlanTask;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClientActions;
use App\Services\FamilyActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clients in the app: records, consent, care plans, family access and
 * sign-in setup. Every change goes through ClientActions / FamilyActions,
 * the same code the website uses. Reading a client record is audited.
 */
class ClientController extends Controller
{
    public function __construct(private ClientActions $clients, private FamilyActions $family) {}

    // ---- Clients ----------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $clients = Patient::query()
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('area', 'like', "%{$term}%")))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("status = 'closed'")->orderBy('name')
            ->limit(200)->get();

        return response()->json([
            'clients' => $clients->map(fn (Patient $p) => [
                'id'      => $p->id,
                'code'    => $p->code,
                'name'    => $p->name,
                'area'    => $p->area,
                'status'  => $p->status,
                'consent' => $p->hasConsent(),
            ])->values(),
        ]);
    }

    public function show(Request $request, Patient $patient): JsonResponse
    {
        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} (app)");

        return response()->json($this->detail($patient));
    }

    public function store(Request $request): JsonResponse
    {
        $patient = $this->clients->create($request);

        return response()->json(['message' => "Client {$patient->code} created."] + $this->detail($patient), 201);
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        $message = $this->clients->update($request, $patient);

        return response()->json(['message' => $message] + $this->detail($patient->fresh()));
    }

    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        return response()->json(['message' => $this->clients->archive($request, $patient)]);
    }

    // ---- Care plans -------------------------------------------------------

    public function startPlan(Request $request, Patient $patient): JsonResponse
    {
        [$plan, $message] = $this->clients->startPlan($request, $patient);

        return response()->json(['message' => $message] + $this->plan($plan));
    }

    public function showPlan(Request $request, CarePlan $carePlan): JsonResponse
    {
        AuditLog::record($request, 'viewed', 'care_plan', $carePlan->id, "{$carePlan->patient?->code} v{$carePlan->version} (app)");

        return response()->json($this->plan($carePlan));
    }

    public function savePlan(Request $request, CarePlan $carePlan): JsonResponse
    {
        $message = $this->clients->saveDraft($request, $carePlan);

        return response()->json(['message' => $message] + $this->plan($carePlan->fresh()));
    }

    public function activatePlan(Request $request, CarePlan $carePlan): JsonResponse
    {
        $message = $this->clients->activatePlan($request, $carePlan);

        return response()->json(['message' => $message] + $this->plan($carePlan->fresh()));
    }

    public function discardPlan(Request $request, CarePlan $carePlan): JsonResponse
    {
        return response()->json(['message' => $this->clients->discardDraft($request, $carePlan)]);
    }

    // ---- Family and sign-in -----------------------------------------------

    public function addFamily(Request $request, Patient $patient): JsonResponse
    {
        [, $message] = $this->family->link($request, $patient);

        return response()->json(['message' => $message] + $this->detail($patient->fresh()));
    }

    public function updateFamily(Request $request, Guardian $guardian): JsonResponse
    {
        $message = $this->family->update($request, $guardian);

        return response()->json(['message' => $message] + $this->detail($guardian->patient->fresh()));
    }

    public function removeFamily(Request $request, Guardian $guardian): JsonResponse
    {
        $patient = $guardian->patient;
        $message = $this->family->unlink($request, $guardian);

        return response()->json(['message' => $message] + $this->detail($patient->fresh()));
    }

    /** Shown once on the phone; stored only as a hash. */
    public function temporaryPassword(Request $request, User $user): JsonResponse
    {
        $credentials = $this->family->issueTemporaryPassword($request, $user);

        return response()->json(['message' => 'Temporary password issued. It is shown only once.', 'credentials' => $credentials]);
    }

    public function clientLogin(Request $request, Patient $patient): JsonResponse
    {
        $credentials = $this->family->createClientLogin($request, $patient);

        return response()->json(['message' => 'Sign-in created. The password is shown only once.', 'credentials' => $credentials]);
    }

    /** What the forms need to offer: statuses, mobility levels, task choices. */
    public function options(): JsonResponse
    {
        return response()->json([
            'statuses'    => ClientActions::STATUSES,
            'mobility'    => ClientActions::MOBILITY,
            'categories'  => CarePlanTask::CATEGORIES,
            'frequencies' => CarePlanTask::FREQUENCIES,
            'times'       => CarePlanTask::TIMES,
        ]);
    }

    // ---- Shapes -----------------------------------------------------------

    private function detail(Patient $p): array
    {
        $p->load(['guardians.user', 'user', 'carePlans' => fn ($q) => $q->withCount('tasks')->orderByDesc('version'),
                  'assignments' => fn ($q) => $q->with(['caregiver.user', 'service'])->orderByDesc('start_date')]);

        return [
            'id'               => $p->id,
            'code'             => $p->code,
            'name'             => $p->name,
            'status'           => $p->status,
            'ic_number'        => $p->ic_number,
            'dob'              => $p->dob?->toDateString(),
            'age'              => $p->dob?->age,
            'gender'           => $p->gender,
            'address'          => $p->address,
            'area'             => $p->area,
            'postcode'         => $p->postcode,
            'mobility_level'   => $p->mobility_level,
            'languages'        => $p->languages,
            'allergies'        => $p->allergies,
            'notes'            => $p->notes,
            'consent_given_at' => $p->consent_given_at?->toDateString(),
            'consent_by'       => $p->consent_by,
            'login'            => $p->user ? ['id' => $p->user->id, 'email' => $p->user->email, 'status' => $p->user->status] : null,
            'care_plans'       => $p->carePlans->map(fn (CarePlan $c) => [
                'id'             => $c->id,
                'version'        => $c->version,
                'status'         => $c->status,
                'effective_from' => $c->effective_from?->toDateString(),
                'agreed_by'      => $c->agreed_by,
                'tasks'          => $c->tasks_count,
            ])->values(),
            'family' => $p->guardians->sortByDesc('is_primary')->map(fn (Guardian $g) => [
                'id'                  => $g->id,
                'user_id'             => $g->user_id,
                'name'                => $g->user?->name,
                'email'               => $g->user?->email,
                'phone'               => $g->user?->phone,
                'login_status'        => $g->user?->status,
                'relationship'        => $g->relationship,
                'is_primary'          => $g->is_primary,
                'is_bill_payer'       => $g->is_bill_payer,
                'can_view_notes'      => $g->can_view_notes,
                'can_view_invoices'   => $g->can_view_invoices,
                'can_request_changes' => $g->can_request_changes,
            ])->values(),
            'assignments' => $p->assignments->map(fn ($a) => [
                'id'         => $a->id,
                'caregiver'  => $a->caregiver?->user?->name,
                'service'    => $a->service?->name,
                'status'     => $a->status,
                'start_date' => $a->start_date?->toDateString(),
                'end_date'   => $a->end_date?->toDateString(),
            ])->values(),
        ];
    }

    private function plan(CarePlan $c): array
    {
        $c->load(['tasks', 'patient', 'author']);

        return [
            'id'             => $c->id,
            'patient_id'     => $c->patient_id,
            'client'         => $c->patient?->name,
            'consent'        => (bool) $c->patient?->hasConsent(),
            'version'        => $c->version,
            'status'         => $c->status,
            'effective_from' => $c->effective_from?->toDateString(),
            'effective_to'   => $c->effective_to?->toDateString(),
            'agreed_by'      => $c->agreed_by,
            'agreed_at'      => $c->agreed_at?->toDateString(),
            'author'         => $c->author?->name,
            'notes'          => $c->notes,
            'tasks'          => $c->tasks->map(fn (CarePlanTask $t) => $t->only(['id', 'category', 'description', 'frequency', 'time_of_day']))->values(),
        ];
    }
}
