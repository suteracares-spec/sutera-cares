<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Every concern lands here: flagged by a caregiver at check-out, raised by
 * a family member or a client, or a care plan change request. Each has an
 * owner until it is resolved, so nothing raised is left with nobody.
 */
class ConcernController extends Controller
{
    public function index(Request $request): View
    {
        $show = $request->query('show', 'open');

        return view('admin.concerns.index', [
            'show'     => $show,
            'concerns' => Concern::query()->with(['patient', 'raisedBy', 'assignedTo'])
                ->when($show === 'open', fn ($q) => $q->whereIn('status', ['open', 'investigating']))
                ->when($show === 'mine', fn ($q) => $q->whereIn('status', ['open', 'investigating'])->where('assigned_to_id', $request->user()->id))
                ->when($show === 'closed', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
                ->orderByRaw("status = 'open' desc")->latest()
                ->paginate(30)->withQueryString(),
        ]);
    }

    public function show(Request $request, Concern $concern): View
    {
        $concern->load(['patient', 'raisedBy', 'assignedTo', 'shift.assignment.caregiver.user', 'shift.visitLog']);
        AuditLog::record($request, 'viewed', 'concern', $concern->id, $concern->patient?->code);

        return view('admin.concerns.show', [
            'concern' => $concern,
            'staff'   => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_COORDINATOR])->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Concern $concern): RedirectResponse
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

        return back()->with('status', 'Concern updated.');
    }
}
