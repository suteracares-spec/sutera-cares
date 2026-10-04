<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaregiverVisitTest extends TestCase
{
    use RefreshDatabase;

    private Caregiver $siti;

    private Caregiver $nurul;

    private Patient $patient;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 07:30');

        $this->siti = $this->caregiver('Siti', 'CG-001');
        $this->nurul = $this->caregiver('Nurul', 'CG-002');

        $this->patient = Patient::create([
            'code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active', 'address' => '12 Jalan Mawar',
            'allergies' => 'Penicillin', 'ic_number' => '450101-10-1234', 'notes' => 'Office-only note',
        ]);
        $plan = $this->patient->carePlans()->create(['version' => 1, 'effective_from' => today(), 'status' => 'active']);
        $plan->tasks()->create(['category' => 'personal_care', 'description' => 'Bed bath', 'sort_order' => 0]);
        $plan->tasks()->create(['category' => 'household', 'description' => 'Prepare lunch', 'sort_order' => 1]);

        $service = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $assignment = $this->patient->assignments()->create([
            'caregiver_id' => $this->siti->id, 'service_id' => $service->id, 'start_date' => today(), 'status' => 'active',
        ]);
        $this->shift = $assignment->shifts()->create(['shift_date' => today(), 'start_time' => '08:00', 'end_time' => '13:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function caregiver(string $name, string $code): Caregiver
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower($name) . '@example.test', 'password' => 'password',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active',
        ]);

        return Caregiver::create(['user_id' => $user->id, 'code' => $code, 'status' => 'active',
            'right_to_work_verified' => true, 'police_check_expires_at' => now()->addYear()]);
    }

    public function test_a_full_visit_check_in_tasks_note_check_out(): void
    {
        $this->actingAs($this->siti->user)->get(route('caregiver.dashboard'))->assertOk()->assertSee('Puan Aminah');

        $this->get(route('caregiver.shifts.show', $this->shift))->assertOk()
            ->assertSee('Penicillin')->assertSee('I have arrived')
            ->assertDontSee('450101-10-1234')->assertDontSee('Office-only note');

        $this->post(route('caregiver.shifts.check-in', $this->shift), ['lat' => '3.0738', 'lng' => '101.5183'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $this->shift->fresh()->status);
        $this->assertEquals(3.0738, (float) $this->shift->visitLog->check_in_lat);

        Carbon::setTestNow('2026-10-05 12:55');
        $bath = $this->patient->activeCarePlan()->tasks()->where('description', 'Bed bath')->value('id');

        $this->post(route('caregiver.shifts.check-out', $this->shift), ['tasks' => [], 'notes' => ''])
            ->assertSessionHasErrors('notes');

        $this->post(route('caregiver.shifts.check-out', $this->shift), [
            'tasks' => [$bath], 'notes' => 'Ate well, cheerful.', 'concern_flagged' => '0',
        ])->assertRedirect(route('caregiver.dashboard'));

        $log = $this->shift->fresh()->visitLog;
        $this->assertSame('completed', $this->shift->fresh()->status);
        $this->assertSame(['Bed bath'], $log->tasks_completed);
        $this->assertSame(325, $log->minutes_worked);
        $this->assertStringNotContainsString('cheerful', DB::table('visit_logs')->value('notes'), 'Notes are encrypted at rest.');
        $this->assertSame(0, Concern::count());
    }

    public function test_a_flagged_concern_reaches_the_office(): void
    {
        $this->actingAs($this->siti->user)->post(route('caregiver.shifts.check-in', $this->shift));

        $this->post(route('caregiver.shifts.check-out', $this->shift), [
            'notes' => 'Visit done.', 'concern_flagged' => '1', 'concern_category' => 'safety',
        ])->assertSessionHasErrors('concern_detail');

        $this->post(route('caregiver.shifts.check-out', $this->shift), [
            'notes' => 'Visit done.', 'concern_flagged' => '1', 'concern_category' => 'safety',
            'concern_detail' => 'Bruise on left arm, says she slipped yesterday.',
        ])->assertSessionHasNoErrors();

        $concern = Concern::firstOrFail();
        $this->assertSame('safety', $concern->category);
        $this->assertSame($this->patient->id, $concern->patient_id);
        $this->assertSame('open', $concern->status);
        $this->assertTrue($this->shift->fresh()->visitLog->concern_flagged);
    }

    public function test_a_caregiver_sees_only_their_own_shifts(): void
    {
        $this->actingAs($this->nurul->user)->get(route('caregiver.shifts.show', $this->shift))->assertNotFound();
        $this->post(route('caregiver.shifts.check-in', $this->shift))->assertNotFound();
        $this->get(route('caregiver.dashboard'))->assertOk()->assertDontSee('Puan Aminah');

        // Covering the shift makes it Nurul's, and no longer Siti's.
        $this->shift->update(['covered_by_id' => $this->nurul->id]);
        $this->get(route('caregiver.shifts.show', $this->shift))->assertOk();
        $this->actingAs($this->siti->user)->get(route('caregiver.shifts.show', $this->shift))->assertNotFound();

        // And the office pages are not theirs at all.
        $this->get(route('admin.patients.show', $this->patient))->assertForbidden();
    }

    public function test_check_in_opens_an_hour_before_and_not_on_cancelled_shifts(): void
    {
        Carbon::setTestNow('2026-10-05 06:30');
        $this->actingAs($this->siti->user)->post(route('caregiver.shifts.check-in', $this->shift))
            ->assertSessionHasErrors('check_in');

        $this->shift->update(['status' => 'cancelled', 'cancel_reason' => 'Family away']);
        Carbon::setTestNow('2026-10-05 08:00');
        $this->post(route('caregiver.shifts.check-in', $this->shift))->assertSessionHasErrors('check_in');
        $this->assertNull($this->shift->fresh()->visitLog);
    }

    public function test_the_office_corrects_a_record_with_a_reason_and_marks_missed_shifts(): void
    {
        $staff = User::create(['name' => 'Coord', 'email' => 'c@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);

        $this->actingAs($staff)->post(route('admin.shifts.correct', $this->shift), [
            'check_in_at' => '2026-10-05T08:05', 'check_out_at' => '2026-10-05T13:00',
        ])->assertSessionHasErrors('reason');

        $this->post(route('admin.shifts.correct', $this->shift), [
            'check_in_at' => '2026-10-05T08:05', 'check_out_at' => '2026-10-05T13:00', 'reason' => 'Phone died; family confirmed',
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $this->shift->fresh()->status);
        $this->assertSame(295, $this->shift->visitLog->minutes_worked);
        $this->assertSame($this->siti->id, $this->shift->visitLog->caregiver_id);
        $this->assertTrue(AuditLog::where('action', 'visit_corrected')->where('detail', 'like', '%Phone died%')->exists());

        $tomorrowAssignment = $this->shift->assignment;
        $past = $tomorrowAssignment->shifts()->create(['shift_date' => '2026-10-02', 'start_time' => '08:00', 'end_time' => '13:00']);
        $this->post(route('admin.shifts.missed', $past), ['reason' => 'Caregiver ill, no relief'])->assertSessionHasNoErrors();
        $this->assertSame('missed', $past->fresh()->status);

        $this->get(route('admin.shifts.show', $this->shift))->assertOk()->assertSee('Correct the visit record');
    }
}
