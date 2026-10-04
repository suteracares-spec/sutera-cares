<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Caregivers and their placements, shared by the website and the app:
 * caregiver records and vetting, assigning a caregiver to a client,
 * one-off visits, booking shifts from a weekly pattern, and ending an
 * assignment. Each method validates its own input from the request.
 */
class PlacementActions
{
    public function __construct(private Scheduler $scheduler) {}

    // ---- Caregivers ---------------------------------------------------------

    /** A caregiver is a person who signs in: the login and the staff record are made together. */
    public function createCaregiver(Request $request): Caregiver
    {
        $data = $this->validatedCaregiver($request);

        $caregiver = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make(Str::password(16)),   // replaced when they set their own
                'role'     => User::ROLE_CAREGIVER,
                'status'   => 'invited',
            ]);

            return Caregiver::create($this->staffFields($data) + ['user_id' => $user->id, 'code' => Caregiver::nextCode()]);
        });

        AuditLog::record($request, 'created', 'caregiver', $caregiver->id, $caregiver->code);

        return $caregiver;
    }

    public function updateCaregiver(Request $request, Caregiver $caregiver): string
    {
        $data = $this->validatedCaregiver($request, $caregiver);

        DB::transaction(function () use ($caregiver, $data) {
            $caregiver->user->update([
                'name'   => $data['name'],
                'email'  => $data['email'],
                'phone'  => $data['phone'] ?? null,
                // Leaving keeps the record but revokes the building. Someone
                // still invited stays invited: they have not chosen their own
                // password yet, and saving their record should not skip that.
                'status' => match (true) {
                    in_array($data['status'], ['left', 'inactive'], true) => 'suspended',
                    $caregiver->user->status === 'invited'                => 'invited',
                    default                                               => 'active',
                },
            ]);
            $caregiver->update($this->staffFields($data));
        });

        AuditLog::record($request, 'updated', 'caregiver', $caregiver->id, $caregiver->code);

        return 'Caregiver record updated.';
    }

    /** Archive: access revoked, record kept, because past visit logs are evidence. */
    public function archiveCaregiver(Request $request, Caregiver $caregiver): string
    {
        DB::transaction(function () use ($caregiver) {
            $caregiver->user->update(['status' => 'suspended']);
            $caregiver->update(['status' => 'left']);
            $caregiver->delete();
        });

        AuditLog::record($request, 'archived', 'caregiver', $caregiver->id, $caregiver->code);

        return "Caregiver {$caregiver->code} archived and access revoked.";
    }

    /** Caregivers who can be sent today, by name. */
    public function placeable(): Collection
    {
        return Caregiver::with('user')->where('status', 'active')->get()
            ->filter->isPlaceable()
            ->sortBy(fn ($c) => $c->user?->name)
            ->values();
    }

    private function validatedCaregiver(Request $request, ?Caregiver $caregiver = null): array
    {
        return $request->validate([
            'name'                    => ['required', 'string', 'max:150'],
            'email'                   => ['required', 'email', 'max:190',
                                          Rule::unique('users', 'email')->ignore($caregiver?->user_id)],
            'phone'                   => ['nullable', 'string', 'max:30'],
            'ic_number'               => ['nullable', 'string', 'max:20'],
            'gender'                  => ['nullable', 'in:female,male,other'],
            'dob'                     => ['nullable', 'date', 'before:today'],
            'languages'               => ['nullable', 'string', 'max:200'],
            'skills'                  => ['nullable', 'string', 'max:500'],
            'base_area'               => ['nullable', 'string', 'max:120'],
            'has_own_transport'       => ['nullable', 'boolean'],
            'max_travel_km'           => ['nullable', 'integer', 'min:0', 'max:500'],
            'hourly_rate'             => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'police_check_expires_at' => ['nullable', 'date'],
            'right_to_work_verified'  => ['nullable', 'boolean'],
            'status'                  => ['required', 'in:applicant,vetting,active,inactive,left'],
        ]);
    }

    /** The fields that belong on the staff record rather than the login. */
    private function staffFields(array $data): array
    {
        return [
            'ic_number'               => $data['ic_number'] ?? null,
            'gender'                  => $data['gender'] ?? null,
            'dob'                     => $data['dob'] ?? null,
            'languages'               => $data['languages'] ?? null,
            'skills'                  => $data['skills'] ?? null,
            'base_area'               => $data['base_area'] ?? null,
            'has_own_transport'       => (bool) ($data['has_own_transport'] ?? false),
            'max_travel_km'           => $data['max_travel_km'] ?? null,
            'hourly_rate'             => $data['hourly_rate'] ?? null,
            'police_check_expires_at' => $data['police_check_expires_at'] ?? null,
            'right_to_work_verified'  => (bool) ($data['right_to_work_verified'] ?? false),
            'status'                  => $data['status'],
        ];
    }

    // ---- Assignments ----------------------------------------------------------

    /**
     * Place a caregiver with a client: an ongoing assignment, or a one-off
     * visit (`one_off`), which is the same thing on a single day.
     *
     * @return array{0: Assignment, 1: string}
     */
    public function createAssignment(Request $request, Patient $patient): array
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

        return [$assignment, $oneOff
            ? 'Visit booked.'
            : 'Assignment created. Now add the shifts: the days and times the caregiver goes.'];
    }

    /** Confirm a proposed placement, or change the agreed rate and notes. */
    public function updateAssignment(Request $request, Assignment $assignment): string
    {
        if ($assignment->status === 'ended') {
            throw ValidationException::withMessages(['status' => 'This assignment has ended.']);
        }

        $data = $request->validate([
            'status'      => ['required', 'in:proposed,active'],
            'charge_rate' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $assignment->update($data);
        AuditLog::record($request, 'updated', 'assignment', $assignment->id, $assignment->patient?->code);

        return 'Assignment updated.';
    }

    /** End it: set the last day and cancel anything booked after it. */
    public function endAssignment(Request $request, Assignment $assignment): string
    {
        $data = $request->validate([
            'last_day' => ['required', 'date', 'after_or_equal:' . $assignment->start_date->toDateString()],
            'reason'   => ['required', 'string', 'max:200'],
        ]);

        $cancelled = $this->scheduler->end($assignment, Carbon::parse($data['last_day']), 'Assignment ended: ' . $data['reason']);

        AuditLog::record($request, 'ended', 'assignment', $assignment->id, $data['reason']);

        return 'Assignment ended'
            . ($cancelled ? " and {$cancelled} later " . str('shift')->plural($cancelled) . ' cancelled.' : '.');
    }

    // ---- Booking shifts -------------------------------------------------------

    /** Book a weekly pattern, e.g. Mon–Fri 08:00–13:00 for the next four weeks. */
    public function generateShifts(Request $request, Assignment $assignment): string
    {
        $this->refuseIfEnded($assignment);

        $data = $request->validate([
            'weekdays'   => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'from'       => ['required', 'date'],
            'until'      => ['required', 'date', 'after_or_equal:from',
                             'before_or_equal:' . Carbon::parse($request->input('from', 'today'))->addWeeks(26)->toDateString()],
        ], [
            'weekdays.required'     => 'Tick at least one day of the week.',
            'end_time.after'        => 'Shifts end after they start. Overnight care is booked as two shifts either side of midnight.',
            'until.before_or_equal' => 'Book at most 26 weeks at a time.',
        ]);

        $result = $this->scheduler->generate($assignment, array_map('intval', $data['weekdays']),
            $data['start_time'], $data['end_time'], $data['from'], $data['until']);

        AuditLog::record($request, 'shifts_generated', 'assignment', $assignment->id, "{$result['created']} shifts");

        $message = $result['created'] . ' ' . str('shift')->plural($result['created']) . ' booked.';
        if ($result['duplicates']) {
            $message .= ' Already booked, skipped: ' . implode(', ', $result['duplicates']) . '.';
        }
        if ($result['clashes']) {
            $message .= ' NOT booked because the caregiver is busy: ' . implode(', ', $result['clashes'])
                . '. Book relief for those days.';
        }

        return $message;
    }

    /** One extra shift on one day. */
    public function addShift(Request $request, Assignment $assignment): Shift
    {
        $this->refuseIfEnded($assignment);

        $data = $request->validate([
            'shift_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
        ], ['end_time.after' => 'The shift must end after it starts.']);
        $data['start_time'] = Shift::normaliseTime($data['start_time']);
        $data['end_time'] = Shift::normaliseTime($data['end_time']);

        if (! $assignment->covers(Carbon::parse($data['shift_date']))) {
            throw ValidationException::withMessages(['shift_date' => 'That day is outside the assignment\'s dates.']);
        }
        if ($clash = $this->scheduler->clash($assignment->caregiver_id, $data['shift_date'], $data['start_time'], $data['end_time'])) {
            throw ValidationException::withMessages([
                'start_time' => "That caregiver is already booked {$clash->timeRange()} that day with {$clash->assignment?->patient?->code}.",
            ]);
        }

        $shift = $assignment->shifts()->create($data + ['status' => 'scheduled']);
        AuditLog::record($request, 'created', 'shift', $shift->id, $assignment->patient?->code);

        return $shift;
    }

    private function refuseIfEnded(Assignment $assignment): void
    {
        if ($assignment->status === 'ended') {
            throw ValidationException::withMessages(['shift_date' => 'This assignment has ended. Start a new one to book more shifts.']);
        }
    }
}
