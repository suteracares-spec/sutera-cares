<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\JobApplication;
use App\Services\OfficeActions;
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

    public function updateStatus(Request $request, Enquiry $enquiry, OfficeActions $actions): RedirectResponse
    {
        return back()->with('status', $actions->enquiryStatus($request, $enquiry));
    }

    /** Turn an enquiry into a client record, keeping the enquiry pointing at it. */
    public function convert(Request $request, Enquiry $enquiry, OfficeActions $actions): RedirectResponse
    {
        $already = (bool) $enquiry->patient_id;
        $patient = $actions->convertEnquiry($request, $enquiry);

        return $already
            ? redirect()->route('admin.patients.show', $patient)->with('status', 'This enquiry was already converted.')
            : redirect()->route('admin.patients.edit', $patient)
                ->with('status', "Client {$patient->code} created from the enquiry. Record consent before care begins.");
    }
}
