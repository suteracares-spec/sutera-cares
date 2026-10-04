<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\FamilyView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The family's view, and the one families judge the service by: who is
 * coming this week, what happened on each visit, what they owe, and a way
 * to reach a person. What each relative sees is set per link by the
 * office; visit notes and invoices are switched on, not assumed.
 */
class FamilyController extends Controller
{
    public function __construct(private FamilyView $family) {}

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
        $link = $this->family->link($request, $patient);
        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} by family");

        return view('family.show', [
            'patient'   => $patient,
            'link'      => $link,
            'coming'    => $this->family->coming($patient),
            'visits'    => $this->family->visits($patient),
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
        $link = $this->family->link($request, $invoice->patient);
        abort_unless($link->can_view_invoices && ! in_array($invoice->status, ['draft', 'void'], true), 404);

        AuditLog::record($request, 'viewed', 'invoice', $invoice->id, "{$invoice->number} by family");

        return view('invoices.print', ['invoice' => $invoice->load(['patient', 'billTo', 'lines', 'payments'])]);
    }

    /** Raise a concern, or ask for a change to the care plan. Both reach the office's inbox. */
    public function concern(Request $request, Patient $patient): RedirectResponse
    {
        $link = $this->family->link($request, $patient);

        $this->family->raiseConcern($request, $patient, $link->can_request_changes, 'family');

        return back()->with('status', 'Thank you. The office has it and your coordinator will be in touch.');
    }
}
