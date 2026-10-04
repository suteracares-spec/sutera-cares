<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Enquiry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Concern and enquiry handling, shared by the website and the app so both
 * apply the same rules and leave the same audit trail.
 */
class OfficeActions
{
    public const ENQUIRY_STATUSES = ['new', 'contacted', 'assessment_booked', 'converted', 'declined', 'lost'];

    /**
     * Take ownership, move a concern along, resolve it. Resolving or
     * closing needs a record of what was done.
     */
    public function updateConcern(Request $request, Concern $concern): string
    {
        $data = $request->validate([
            'status'         => ['required', 'in:open,investigating,resolved,closed'],
            'assigned_to_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', [User::ROLE_ADMIN, User::ROLE_COORDINATOR])],
            'resolution'     => ['required_if:status,resolved,closed', 'nullable', 'string', 'max:2000'],
        ], ['resolution.required_if' => 'Record what was done before resolving it.']);

        $closing = in_array($data['status'], ['resolved', 'closed'], true);

        $concern->update($data + [
            'resolved_at' => $closing ? ($concern->resolved_at ?? now()) : null,
        ]);

        AuditLog::record($request, 'concern_updated', 'concern', $concern->id, $data['status']);

        return 'Concern updated.';
    }

    public function enquiryStatus(Request $request, Enquiry $enquiry): string
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::ENQUIRY_STATUSES)]]);

        $enquiry->update($data);
        AuditLog::record($request, 'enquiry_updated', 'enquiry', $enquiry->id, $data['status']);

        return "Enquiry from {$enquiry->client_name} marked " . str_replace('_', ' ', $data['status']) . '.';
    }

    /**
     * Turn an enquiry into a client record. The enquiry keeps its own row
     * and points at the client, so the funnel stays auditable rather than
     * the lead quietly disappearing on conversion. Converting twice
     * returns the client already made.
     */
    public function convertEnquiry(Request $request, Enquiry $enquiry): Patient
    {
        if ($enquiry->patient_id && ($existing = Patient::find($enquiry->patient_id))) {
            return $existing;
        }

        $patient = Patient::create([
            'code'   => Patient::nextCode(),
            'name'   => $enquiry->patient_name ?: $enquiry->client_name,
            'area'   => $enquiry->patient_area,
            'notes'  => $enquiry->needs,
            'status' => 'assessment',
        ]);

        $enquiry->update(['status' => 'converted', 'patient_id' => $patient->id]);

        AuditLog::record($request, 'created', 'patient', $patient->id,
            $patient->code . ' (converted from enquiry #' . $enquiry->id . ')');

        return $patient;
    }
}
