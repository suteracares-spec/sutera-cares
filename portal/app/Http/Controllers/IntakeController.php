<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two public forms on provider.suteracares.org.
 *
 * These are the only unauthenticated write endpoints in the portal, so
 * they are deliberately narrow: rate limited at the route, validated
 * strictly, and they touch nothing but their own table. A honeypot field
 * absorbs the ordinary bot traffic any public form attracts.
 */
class IntakeController extends Controller
{
    public function enquiry(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {          // honeypot; humans never fill it
            return $this->done('enquiry');           // answer as if accepted
        }

        $data = $request->validate([
            'client_name'         => ['required', 'string', 'max:150'],
            'client_phone'        => ['required', 'string', 'max:30'],
            'client_email'        => ['nullable', 'email', 'max:190'],
            'client_relationship' => ['nullable', 'string', 'max:80'],
            'patient_name'        => ['nullable', 'string', 'max:150'],
            'patient_age'         => ['nullable', 'integer', 'min:0', 'max:120'],
            'patient_area'        => ['nullable', 'string', 'max:120'],
            'patient_mobility'    => ['nullable', 'string', 'max:60'],
            'needs'               => ['nullable', 'string', 'max:3000'],
            'schedule_wanted'     => ['nullable', 'string', 'max:200'],
        ]);

        Enquiry::create($data + ['source' => 'website', 'status' => 'new']);

        return $this->done('enquiry');
    }

    public function application(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return $this->done('application');
        }

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:150'],
            'phone'            => ['required', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:190'],
            'area'             => ['nullable', 'string', 'max:120'],
            'role_applied'     => ['nullable', 'string', 'max:120'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'availability'     => ['nullable', 'string', 'max:60'],
            'languages'        => ['nullable', 'string', 'max:200'],
            'notes'            => ['nullable', 'string', 'max:3000'],
        ]);

        JobApplication::create($data + ['status' => 'new']);

        return $this->done('application');
    }

    /**
     * Send the visitor back to the marketing site with a marker in the URL.
     *
     * The forms live on a static site the portal does not render, so a
     * Laravel flash message would never be seen. A query parameter the
     * static page can read is the honest way to confirm receipt.
     */
    private function done(string $what): RedirectResponse
    {
        $site = rtrim((string) config('app.marketing_url'), '/');

        return redirect()->away($site . '/?sent=' . $what . '#' . ($what === 'enquiry' ? 'quote' : 'careers'));
    }
}
