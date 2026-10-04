<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The office overview in the app, for administrators and coordinators:
 * one day across every caregiver, and the concerns waiting for someone.
 * The views; changes go through OfficeActionsController.
 */
class OfficeController extends Controller
{
    /** A shift not checked into this long after its start is late. */
    private const LATE_AFTER_MINUTES = 15;

    public function day(Request $request): JsonResponse
    {
        $date = rescue(fn () => Carbon::parse($request->query('date', 'today')), today(), false)->startOfDay();

        $shifts = Shift::query()
            ->with(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog'])
            ->whereDate('shift_date', $date->toDateString())
            ->orderBy('start_time')
            ->get()
            ->map(fn (Shift $s) => $this->shiftSummary($s));

        return response()->json([
            'date'   => $date->toDateString(),
            'counts' => [
                'total'     => $shifts->where('status', '!=', 'cancelled')->count(),
                'attention' => $shifts->whereIn('attention', ['late', 'no_show'])->count(),
                'on_now'    => $shifts->where('status', 'in_progress')->count(),
                'done'      => $shifts->where('status', 'completed')->count(),
            ],
            'open_concerns' => Concern::whereIn('status', ['open', 'investigating'])->count(),
            'shifts'        => $shifts->values(),
        ]);
    }

    public function shift(Request $request, Shift $shift): JsonResponse
    {
        $shift->load(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog']);
        AuditLog::record($request, 'viewed', 'shift', $shift->id, $shift->assignment?->patient?->code . ' (app)');

        $log = $shift->visitLog;
        $patient = $shift->assignment->patient;

        return response()->json($this->shiftSummary($shift) + [
            'cancel_reason' => in_array($shift->status, ['cancelled', 'missed'], true) ? $shift->cancel_reason : null,
            'client_details' => [
                'name'      => $patient->name,
                'code'      => $patient->code,
                'area'      => $patient->area,
                'address'   => $patient->address,
                'allergies' => $patient->allergies,
            ],
            'visit' => $log ? [
                'check_in_at'     => $log->check_in_at?->toIso8601String(),
                'check_out_at'    => $log->check_out_at?->toIso8601String(),
                'location'        => $log->check_in_lat !== null,
                'minutes_worked'  => $log->minutes_worked,
                'tasks_completed' => $log->tasks_completed ?? [],
                'notes'           => $log->notes,
                'concern_flagged' => $log->concern_flagged,
                'concern_detail'  => $log->concern_detail,
            ] : null,
        ]);
    }

    public function concerns(Request $request): JsonResponse
    {
        $open = $request->query('show', 'open') === 'open';

        $concerns = Concern::query()->with(['patient', 'raisedBy', 'assignedTo'])
            ->when($open, fn ($q) => $q->whereIn('status', ['open', 'investigating']))
            ->when(! $open, fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->orderByRaw("status = 'open' desc")->latest()
            ->limit(100)->get();

        return response()->json(['concerns' => $concerns->map(fn (Concern $c) => $this->concernSummary($c))->values()]);
    }

    public function concern(Request $request, Concern $concern): JsonResponse
    {
        $concern->load(['patient', 'raisedBy', 'assignedTo', 'shift']);
        AuditLog::record($request, 'viewed', 'concern', $concern->id, $concern->patient?->code . ' (app)');

        return response()->json($this->concernSummary($concern) + [
            'detail'      => $concern->detail,
            'resolution'  => $concern->resolution,
            'resolved_at' => $concern->resolved_at?->toIso8601String(),
            'shift_id'    => $concern->shift_id,
            'shift'       => $concern->shift ? $concern->shift->shift_date->toDateString() . ' ' . $concern->shift->timeRange() : null,
        ]);
    }

    public function shiftSummary(Shift $s): array
    {
        $carer = $s->caregiver();
        $log = $s->visitLog;

        // What needs a coordinator now: nobody checked in after the start,
        // or the shift ended with nobody having come.
        $attention = null;
        if ($s->status === 'scheduled' && ! $log) {
            if (now()->gt($s->endsAt())) {
                $attention = 'no_show';
            } elseif (now()->gt($s->startsAt()->addMinutes(self::LATE_AFTER_MINUTES))) {
                $attention = 'late';
            }
        }

        return [
            'id'          => $s->id,
            'date'        => $s->shift_date->toDateString(),
            'start'       => substr($s->start_time, 0, 5),
            'end'         => substr($s->end_time, 0, 5),
            'status'      => $s->status,
            'attention'   => $attention,
            'client'      => $s->assignment?->patient?->name,
            'area'        => $s->assignment?->patient?->area,
            'caregiver'   => $carer?->user?->name,
            'covering'    => $s->covered_by_id !== null,
            'service'     => $s->assignment?->service?->name,
            'check_in_at' => $log?->check_in_at?->toIso8601String(),
            'concern'     => (bool) $log?->concern_flagged,
        ];
    }

    private function concernSummary(Concern $c): array
    {
        return [
            'id'        => $c->id,
            'status'    => $c->status,
            'category'  => Concern::CATEGORIES[$c->category] ?? $c->category,
            'plan_change' => str_starts_with((string) $c->detail, 'Care plan change requested'),
            'client'    => $c->patient?->name,
            'raised_by' => $c->raisedBy?->name,
            'raised_by_role' => $c->raisedBy?->role === 'guardian' ? 'family' : $c->raisedBy?->role,
            'owner'     => $c->assignedTo?->name,
            'owner_id'  => $c->assigned_to_id,
            'raised_at' => $c->created_at->toIso8601String(),
        ];
    }
}
