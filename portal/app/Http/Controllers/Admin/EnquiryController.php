<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Models\JobApplication;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.enquiries.index', [
            'enquiries'    => Enquiry::when($request->string('status')->toString(),
                                  fn ($q, $s) => $q->where('status', $s))
                                  ->latest()->paginate(20)->withQueryString(),
            'applications' => JobApplication::latest()->limit(10)->get(),
        ]);
    }

    public function updateStatus(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,assessment_booked,converted,declined,lost'],
        ]);

        $enquiry->update($data);

        return back()->with('status', "Enquiry from {$enquiry->client_name} marked {$data['status']}.");
    }

    /**
     * Turn an enquiry into a client record. The enquiry keeps its own row
     * and points at the patient, so the funnel stays auditable rather than
     * the lead quietly disappearing on conversion.
     */
    public function convert(Request $request, Enquiry $enquiry): RedirectResponse
    {
        if ($enquiry->patient_id) {
            return redirect()->route('admin.patients.show', $enquiry->patient_id)
                ->with('status', 'This enquiry was already converted.');
        }

        $patient = Patient::create([
            'code'   => Patient::nextCode(),
            'name'   => $enquiry->patient_name ?: $enquiry->client_name,
            'area'   => $enquiry->patient_area,
            'notes'  => $enquiry->needs,
            'status' => 'assessment',
        ]);

        $enquiry->update(['status' => 'converted', 'patient_id' => $patient->id]);

        AuditLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'created',
            'subject_type' => 'patient',
            'subject_id'   => $patient->id,
            'detail'       => $patient->code . ' (converted from enquiry #' . $enquiry->id . ')',
            'ip_address'   => $request->ip(),
        ]);

        return redirect()->route('admin.patients.edit', $patient)
            ->with('status', "Client {$patient->code} created from the enquiry. Record consent before care begins.");
    }
}
