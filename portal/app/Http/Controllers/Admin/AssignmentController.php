<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Patient;
use App\Models\Service;
use App\Services\Scheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Placing a caregiver with a client: an ongoing assignment, or a one-off
 * visit such as a massage, which is the same thing on a single day.
 */
class AssignmentController extends Controller
{
    public function __construct(private Scheduler $scheduler) {}

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

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $oneOff = $request->boolean('one_off');

        $data = $request->validate([
            'caregiver_id' => ['required', Rule::exists('caregivers', 'id')->whereNull('deleted_at')],
            'service_id'   => ['required', Rule::exists('services', 'id')->where('active', true)],
            'charge_rate'  => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ] + ($oneOff ? [
            'date'         => ['required', 'date'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
        ] : [
            'role'         => ['required', 'in:primary,relief'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'       => ['required', 'in:proposed,active'],
        ]), [
            'end_time.after' => 'The visit must end after it starts.',
        ]);

        $caregiver = Caregiver::findOrFail($data['caregiver_id']);
        $service = Service::findOrFail($data['service_id']);
        $plan = $patient->activeCarePlan();

        // Vetting is not a formality: an unvetted or lapsed caregiver is not
        // sent into someone's home, however short the visit.
        $problems = [];
        if (! $caregiver->isPlaceable()) {
            $problems['caregiver_id'] = "{$caregiver->user?->name} is not placeable: check their vetting before assigning them.";
        }
        if (in_array($service->category, Assignment::NEEDS_CARE_PLAN, true) && ! $plan) {
            $problems['service_id'] = 'Agree and activate a care plan before assigning ongoing care. The caregiver works from it.';
        }
        if ($patient->status === 'closed') {
            $problems['caregiver_id'] = 'This client is closed. Reopen the client record first.';
        }
        if ($oneOff && ($clash = $this->scheduler->clash($caregiver->id, $data['date'], $data['start_time'], $data['end_time']))) {
            $problems['start_time'] = "{$caregiver->user?->name} is already booked {$clash->timeRange()} that day"
                . " with {$clash->assignment?->patient?->code}.";
        }
        if ($problems) {
            throw ValidationException::withMessages($problems);
        }

        $assignment = DB::transaction(function () use ($patient, $caregiver, $service, $plan, $data, $oneOff) {
            $assignment = $patient->assignments()->create([
                'caregiver_id' => $caregiver->id,
                'service_id'   => $service->id,
                'care_plan_id' => $plan?->id,
                'role'         => $oneOff ? 'primary' : $data['role'],
                'start_date'   => $oneOff ? $data['date'] : $data['start_date'],
                'end_date'     => $oneOff ? $data['date'] : ($data['end_date'] ?? null),
                'charge_rate'  => $data['charge_rate'] ?? $service->base_rate,
                'status'       => $oneOff ? 'active' : $data['status'],
                'notes'        => $data['notes'] ?? null,
            ]);

            if ($oneOff) {
                $assignment->shifts()->create([
                    'shift_date' => $data['date'],
                    'start_time' => $data['start_time'],
                    'end_time'   => $data['end_time'],
                    'status'     => 'scheduled',
                ]);
            }

            return $assignment;
        });

        AuditLog::record($request, 'created', 'assignment', $assignment->id,
            "{$patient->code} ← {$caregiver->code}, {$service->code}" . ($oneOff ? ' (one-off)' : ''));

        return redirect()->route('admin.assignments.show', $assignment)
            ->with('status', $oneOff
                ? 'Visit booked.'
                : 'Assignment created. Now add the shifts: the days and times the caregiver goes.');
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
    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_if($assignment->status === 'ended', 403, 'This assignment has ended.');

        $data = $request->validate([
            'status'      => ['required', 'in:proposed,active'],
            'charge_rate' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $assignment->update($data);
        AuditLog::record($request, 'updated', 'assignment', $assignment->id, $assignment->patient?->code);

        return back()->with('status', 'Assignment updated.');
    }

    /** End it: set the last day and cancel anything booked after it. */
    public function end(Request $request, Assignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'last_day' => ['required', 'date', 'after_or_equal:' . $assignment->start_date->toDateString()],
            'reason'   => ['required', 'string', 'max:200'],
        ]);

        $cancelled = $this->scheduler->end($assignment, Carbon::parse($data['last_day']), 'Assignment ended: ' . $data['reason']);

        AuditLog::record($request, 'ended', 'assignment', $assignment->id, $data['reason']);

        return back()->with('status', 'Assignment ended'
            . ($cancelled ? " and {$cancelled} later " . str('shift')->plural($cancelled) . ' cancelled.' : '.'));
    }

    /** Caregivers who can be sent today, by name. */
    private function placeable()
    {
        return Caregiver::with('user')->where('status', 'active')->get()
            ->filter->isPlaceable()
            ->sortBy(fn ($c) => $c->user?->name)
            ->values();
    }
}
