<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caregiver;
use App\Models\Patient;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The week at a glance: who is where, and which shifts need attention. */
class ScheduleController extends Controller
{
    public function __invoke(Request $request): View
    {
        $week = rescue(fn () => Carbon::parse($request->query('week', 'today')), today(), false)->startOfWeek();
        $caregiverId = $request->integer('caregiver') ?: null;
        $patientId = $request->integer('client') ?: null;

        $shifts = Shift::query()
            ->with(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog'])
            ->whereDate('shift_date', '>=', $week->toDateString())
            ->whereDate('shift_date', '<=', $week->copy()->endOfWeek()->toDateString())
            ->when($caregiverId, fn ($q) => $q->workedBy($caregiverId))
            ->when($patientId, fn ($q) => $q->whereHas('assignment', fn ($a) => $a->where('patient_id', $patientId)))
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn ($s) => $s->shift_date->toDateString());

        return view('admin.schedule', [
            'week'        => $week,
            'days'        => collect(range(0, 6))->map(fn ($i) => $week->copy()->addDays($i)),
            'shifts'      => $shifts,
            'caregivers'  => Caregiver::with('user')->whereIn('status', ['active', 'inactive'])->get()->sortBy('user.name'),
            'clients'     => Patient::whereIn('status', ['active', 'assessment', 'paused'])->orderBy('name')->get(),
            'caregiverId' => $caregiverId,
            'patientId'   => $patientId,
        ]);
    }
}
