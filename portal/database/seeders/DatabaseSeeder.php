<?php

namespace Database\Seeders;

use App\Models\Caregiver;
use App\Models\Enquiry;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Service catalogue, matching the published pricing ----------
        $services = [
            ['HOURLY-PC',   'Personal care - hourly visit',          'personal_care', 'hour',      35.00],
            ['DAILY-8H',    'Daily care - 8 hour day',               'daily_care',    'day',      180.00],
            ['LIVEIN-M',    'Live-in care - monthly',                'live_in',       'month',   3500.00],
            ['ESCORT',      'Hospital or clinic appointment escort', 'appointment',   'visit',    120.00],
            ['MASSAGE-60',  'Comfort massage - 60 minutes',          'wellness',      'session',  120.00],
            ['MASSAGE-PN',  'Post-natal massage - 60 minutes',       'wellness',      'session',  150.00],
            ['WELLNESS-ST', 'Assisted stretching session',           'wellness',      'session',   90.00],
        ];

        foreach ($services as [$code, $name, $category, $unit, $rate]) {
            Service::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'unit' => $unit, 'base_rate' => $rate],
            );
        }

        // --- First administrator ----------------------------------------
        // Seeders live in git, so a password written here is public. Locally
        // that is fine. Anywhere else, the first administrator gets a random
        // temporary password, printed once, and must choose their own at
        // first sign-in; an existing administrator is never touched.
        if (! app()->environment('local')) {
            if (User::where('role', User::ROLE_ADMIN)->doesntExist()) {
                $password = \App\Http\Controllers\Admin\SignInController::generate();

                User::create([
                    'name'     => 'Sutera Administrator',
                    'email'    => 'admin@suteracares.org',
                    'password' => Hash::make($password),
                    'role'     => User::ROLE_ADMIN,
                    'status'   => 'invited',
                ]);

                $this->command?->warn("First administrator: admin@suteracares.org / {$password}  (shown once)");
            }

            return;
        }

        User::updateOrCreate(
            ['email' => 'admin@suteracares.org'],
            [
                'name'     => 'Sutera Administrator',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_ADMIN,
                'status'   => 'active',
            ],
        );

        // --- Demo data, local only --------------------------------------
        // An empty dashboard shows nothing about whether it works.
        $patients = [
            ['Puan Aminah binti Hassan', 'Cheras',      'bed_bound',      'active',     true],
            ['Mr Tan Ah Kow',            'Petaling Jaya', 'wheelchair',   'active',     true],
            ['Mrs Lakshmi Devi',         'Klang',       'walks_with_aid', 'assessment', false],
            ['Encik Rahman bin Yusof',   'Ampang',      'independent',    'enquiry',    false],
        ];

        foreach ($patients as [$name, $area, $mobility, $status, $consented]) {
            Patient::create([
                'code'             => Patient::nextCode(),
                'name'             => $name,
                'area'             => $area,
                'mobility_level'   => $mobility,
                'status'           => $status,
                'languages'        => 'Bahasa Malaysia, English',
                'consent_given_at' => $consented ? now()->subDays(random_int(5, 60)) : null,
                'consent_by'       => $consented ? 'Daughter' : null,
            ]);
        }

        // A family member and an agreed plan for the first client, so the
        // client page shows what a client in care looks like.
        $aminah = Patient::where('name', 'like', 'Puan Aminah%')->first();

        $daughter = User::create([
            'name'     => 'Nor Hayati binti Ahmad',
            'email'    => 'hayati@example.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_GUARDIAN,
            'status'   => 'active',
        ]);

        $aminah->guardians()->create([
            'user_id'           => $daughter->id,
            'relationship'      => 'Daughter',
            'is_primary'        => true,
            'is_bill_payer'     => true,
            'can_view_notes'    => true,
            'can_view_invoices' => true,
        ]);

        $plan = $aminah->carePlans()->create([
            'version'        => 1,
            'effective_from' => now()->subDays(30),
            'agreed_by'      => 'Nor Hayati (daughter)',
            'agreed_at'      => now()->subDays(32),
            'notes'          => "Prefers a female caregiver. Hard of hearing on the left side.\nLikes her tea before her bath, not after.",
            'status'         => 'active',
        ]);

        $tasks = [
            ['personal_care', 'Bed bath and change of clothes',              'every_visit', 'morning'],
            ['personal_care', 'Reposition every two hours to prevent sores', 'every_visit', 'any'],
            ['personal_care', 'Remind and record morning medication',        'daily',       'morning'],
            ['household',     'Prepare soft-diet lunch and assist feeding',  'every_visit', 'midday'],
            ['companionship', 'Read the newspaper aloud or play her radio',  'as_needed',   'any'],
        ];

        foreach ($tasks as $i => [$category, $description, $frequency, $time]) {
            $plan->tasks()->create([
                'category'    => $category,
                'description' => $description,
                'frequency'   => $frequency,
                'time_of_day' => $time,
                'sort_order'  => $i,
            ]);
        }

        $caregivers = [
            ['Siti Nurhaliza binti Omar', 'siti@example.test',   'Cheras',        now()->addDays(20)],
            ['Nurul Ain binti Ismail',    'nurul@example.test',  'Petaling Jaya', now()->addMonths(9)],
            ['Kavitha Rajan',             'kavitha@example.test', 'Klang',        now()->subDays(4)],
        ];

        foreach ($caregivers as [$name, $email, $area, $checkExpiry]) {
            $user = User::create([
                'name'     => $name,
                'email'    => $email,
                'password' => Hash::make('password'),
                'role'     => User::ROLE_CAREGIVER,
                'status'   => 'active',
            ]);

            Caregiver::create([
                'user_id'                 => $user->id,
                'code'                    => Caregiver::nextCode(),
                'base_area'               => $area,
                'languages'               => 'Bahasa Malaysia, English',
                'skills'                  => 'personal care, mobility, appointment escort',
                'hourly_rate'             => 18.00,
                'right_to_work_verified'  => true,
                'police_check_expires_at' => $checkExpiry,
                'status'                  => 'active',
            ]);
        }

        // Puan Aminah's placement: Siti on weekday mornings, four weeks
        // either side of today, so the schedule and the visit history both
        // have something in them. Plus a one-off massage for Mr Tan.
        $siti = Caregiver::where('base_area', 'Cheras')->first();
        $placement = $aminah->assignments()->create([
            'caregiver_id' => $siti->id,
            'service_id'   => Service::where('code', 'HOURLY-PC')->value('id'),
            'care_plan_id' => $plan->id,
            'role'         => 'primary',
            'start_date'   => now()->subWeeks(4)->startOfWeek(),
            'charge_rate'  => 35.00,
            'status'       => 'active',
        ]);
        app(\App\Services\Scheduler::class)->generate($placement, [1, 2, 3, 4, 5], '08:00', '13:00',
            now()->subWeeks(4)->startOfWeek()->toDateString(), now()->addWeeks(4)->toDateString());

        // Past mornings happened: checked in and out, tasks done, a note.
        $notes = ['Cheerful, ate all her porridge.', 'Tired today, slept after lunch.', 'Asked about her grandson. Good appetite.'];
        foreach ($placement->shifts()->whereDate('shift_date', '<', today())->get() as $i => $shift) {
            $shift->visitLog()->create([
                'caregiver_id'    => $siti->id,
                'check_in_at'     => $shift->startsAt()->addMinutes(random_int(-5, 10)),
                'check_out_at'    => $shift->endsAt()->addMinutes(random_int(-5, 5)),
                'minutes_worked'  => 300,
                'tasks_completed' => ['Bed bath and change of clothes', 'Remind and record morning medication'],
                'notes'           => $notes[$i % count($notes)],
            ]);
            $shift->update(['status' => 'completed']);
        }

        $tan = Patient::where('name', 'like', 'Mr Tan%')->first();
        $massage = $tan->assignments()->create([
            'caregiver_id' => Caregiver::where('base_area', 'Petaling Jaya')->value('id'),
            'service_id'   => Service::where('code', 'MASSAGE-60')->value('id'),
            'role'         => 'primary',
            'start_date'   => now()->addDays(2),
            'end_date'     => now()->addDays(2),
            'charge_rate'  => 120.00,
            'status'       => 'active',
        ]);
        $massage->shifts()->create(['shift_date' => now()->addDays(2), 'start_time' => '15:00', 'end_time' => '16:00']);

        Enquiry::create([
            'client_name'         => 'Wong Mei Ling',
            'client_phone'        => '+60 12-345 6789',
            'client_relationship' => 'Son or daughter',
            'patient_name'        => 'Wong Fook Cheong',
            'patient_age'         => 79,
            'patient_area'        => 'Subang Jaya',
            'patient_mobility'    => 'Walks with a stick or frame',
            'needs'               => 'Bathing and breakfast each morning, company in the afternoon.',
            'schedule_wanted'     => 'Mon-Fri, 8am-1pm',
        ]);
    }
}
