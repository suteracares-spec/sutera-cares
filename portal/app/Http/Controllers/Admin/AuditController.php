<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who looked at what, and who changed what. The PDPA question this log
 * exists to answer. Read-only, administrators only, and reading it is
 * itself not logged here (that would bury the entries that matter).
 */
class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $entries = AuditLog::query()->with('user')
            ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', $a))
            ->when($request->string('subject')->toString(), function ($q, $subject) {
                // "patient:12" narrows to one record's history.
                [$type, $id] = array_pad(explode(':', $subject, 2), 2, null);
                $q->where('subject_type', $type)->when($id, fn ($q) => $q->where('subject_id', (int) $id));
            })
            ->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('admin.audit', [
            'entries' => $entries,
            'users'   => User::whereIn('id', AuditLog::query()->select('user_id')->distinct())->orderBy('name')->get(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
