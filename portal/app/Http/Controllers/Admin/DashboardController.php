<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caregiver;
use App\Models\Enquiry;
use App\Models\Patient;
use App\Models\Shift;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // What a coordinator needs on opening the page: what is happening
        // today, and what will bite next week if nobody touches it.
        return view('admin.dashboard', [
            'activeClients'   => Patient::where('status', 'active')->count(),
            'inAssessment'    => Patient::where('status', 'assessment')->count(),
            'activeCaregivers' => Caregiver::where('status', 'active')->count(),
            'newEnquiries'    => Enquiry::where('status', 'new')->count(),

            'shiftsToday'     => Shift::whereDate('shift_date', today())->count(),
            'unstaffedToday'  => Shift::whereDate('shift_date', today())
                                      ->where('status', 'scheduled')
                                      ->whereDoesntHave('visitLog')
                                      ->count(),

            // Vetting lapses quietly. Surface it before it bites.
            'checksExpiring'  => Caregiver::whereNotNull('police_check_expires_at')
                                      ->whereDate('police_check_expires_at', '<', now()->addDays(60))
                                      ->where('status', 'active')
                                      ->get(),

            'recentEnquiries' => Enquiry::latest()->limit(6)->get(),
            'consentMissing'  => Patient::whereNull('consent_given_at')
                                      ->whereIn('status', ['active', 'assessment'])
                                      ->count(),
        ]);
    }
}
