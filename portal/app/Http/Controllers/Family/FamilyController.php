<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The family's view, and the one families judge the service by: who is
 * coming this week, what happened on each visit, what they owe, and a way
 * to reach a person. What each relative sees is set per link by the
 * office; visit notes and invoices are switched on, not assumed.
 */
class FamilyController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $links = $request->user()->guardianLinks()->with('patient')->get()->filter->patient;

        // Most families look after one person: go straight there.
        if ($links->count() === 1) {
            return redirect()->route('guardian.clients.show', $links->first()->patient);
        }

        return view('family.index', ['links' => $links]);
    }

    public function show(Request $request, Patient $patient): View
    {
        $link = $this->link($request, $patient);
        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} by family");

        $shifts = fn () => Shift::query()
            ->whereHas('assignment', fn ($q) => $q->where('patient_id', $patient->id))
            ->with(['assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog']);

        return view('family.show', [
            'patient'   => $patient,
            'link'      => $link,
            'coming'    => $shifts()->whereDate('shift_date', '>=', today())->whereDate('shift_date', '<=', today()->addDays(7))
                                    ->whereIn('status', ['scheduled', 'in_progress'])
                                    ->orderBy('shift_date')->orderBy('start_time')->get(),
            'visits'    => $shifts()->whereDate('shift_date', '>=', today()->subDays(14))
                                    ->whereIn('status', ['completed', 'missed', 'in_progress'])
                                    ->orderByDesc('shift_date')->orderByDesc('start_time')->get(),
            'plan'      => $patient->activeCarePlan()?->load('tasks'),
            'invoices'  => $link->can_view_invoices
                ? Invoice::where('patient_id', $patient->id)->whereNotIn('status', ['draft', 'void'])->orderByDesc('issued_at')->limit(12)->get()
                : collect(),
            'concerns'  => Concern::where('patient_id', $patient->id)->where('raised_by_id', $request->user()->id)
                                  ->latest()->limit(10)->get(),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice): View
    {
        $link = $this->link($request, $invoice->patient);
        abort_unless($link->can_view_invoices && ! in_array($invoice->status, ['draft', 'void'], true), 404);

        AuditLog::record($request, 'viewed', 'invoice', $invoice->id, "{$invoice->number} by family");

        return view('invoices.print', ['invoice' => $invoice->load(['patient', 'billTo', 'lines', 'payments'])]);
    }

    /** Raise a concern, or ask for a change to the care plan. Both reach the office's inbox. */
    public function concern(Request $request, Patient $patient): RedirectResponse
    {
        $link = $this->link($request, $patient);

        $kinds = array_keys(Concern::CATEGORIES);
        if ($link->can_request_changes) {
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

        AuditLog::record($request, 'concern_raised', 'concern', $concern->id, $patient->code);

        return back()->with('status', 'Thank you. The office has it and your coordinator will be in touch.');
    }

    /** The link between this family member and this client, or a 404: do not confirm who else we care for. */
    private function link(Request $request, ?Patient $patient): Guardian
    {
        abort_unless($patient, 404);

        return Guardian::where('user_id', $request->user()->id)->where('patient_id', $patient->id)->first() ?? abort(404);
    }
}
