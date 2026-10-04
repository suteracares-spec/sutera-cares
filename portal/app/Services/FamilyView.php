<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * What families and clients see, shared by the website and the app: who
 * is coming, what happened on each visit, and a way to tell the office
 * something. What a relative sees is set per link by the office.
 */
class FamilyView
{
    /** The link between this family member and this client, or a 404: do not confirm who else we care for. */
    public function link(Request $request, ?Patient $patient): Guardian
    {
        abort_unless($patient, 404);

        return Guardian::where('user_id', $request->user()->id)->where('patient_id', $patient->id)->first() ?? abort(404);
    }

    private function shifts(Patient $patient): Builder
    {
        return Shift::query()
            ->whereHas('assignment', fn ($q) => $q->where('patient_id', $patient->id))
            ->with(['assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog']);
    }

    /** Booked in the next seven days. */
    public function coming(Patient $patient): Collection
    {
        return $this->shifts($patient)
            ->whereDate('shift_date', '>=', today())->whereDate('shift_date', '<=', today()->addDays(7))
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('shift_date')->orderBy('start_time')->get();
    }

    /** What happened in the last two weeks. */
    public function visits(Patient $patient): Collection
    {
        return $this->shifts($patient)
            ->whereDate('shift_date', '>=', today()->subDays(14))
            ->whereIn('status', ['completed', 'missed', 'in_progress'])
            ->orderByDesc('shift_date')->orderByDesc('start_time')->get();
    }

    /**
     * Raise a concern, or (when the link allows it) ask for a change to the
     * care plan. Both reach the office's inbox.
     */
    public function raiseConcern(Request $request, Patient $patient, bool $canRequestChange, string $by): Concern
    {
        $kinds = array_keys(Concern::CATEGORIES);
        if ($canRequestChange) {
            $kinds[] = 'plan_change';
        }

        $data = $request->validate([
            'category' => ['required', Rule::in($kinds)],
            'detail'   => ['required', 'string', 'max:2000'],
        ], ['detail.required' => 'Tell us what it is about.']);

        $change = $data['category'] === 'plan_change';

        $concern = Concern::create([
            'raised_by_id' => $request->user()->id,
            'patient_id'   => $patient->id,
            'category'     => $change ? 'other' : $data['category'],
            'detail'       => ($change ? 'Care plan change requested: ' : '') . $data['detail'],
            'status'       => 'open',
        ]);

        AuditLog::record($request, 'concern_raised', 'concern', $concern->id, "{$patient->code} by {$by}");

        return $concern;
    }
}
