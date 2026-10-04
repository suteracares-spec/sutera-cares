<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Caregiver;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Patient $patient;

    private Caregiver $siti;

    private Caregiver $nurul;

    private Service $hourly;

    private Service $massage;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 09:00');   // a Monday

        $this->staff = User::create([
            'name' => 'Coordinator', 'email' => 'coord@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active', 'consent_given_at' => now(),
        ]);
        $plan = $this->patient->carePlans()->create(['version' => 1, 'effective_from' => today(), 'status' => 'active']);
        $plan->tasks()->create(['category' => 'personal_care', 'description' => 'Bed bath']);

        $this->siti = $this->caregiver('Siti', 'CG-001');
        $this->nurul = $this->caregiver('Nurul', 'CG-002');

        $this->hourly = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $this->massage = Service::create(['code' => 'MASSAGE-60', 'name' => 'Massage', 'category' => 'wellness', 'unit' => 'session', 'base_rate' => 120]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function caregiver(string $name, string $code, bool $placeable = true): Caregiver
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower($name) . '@example.test', 'password' => 'password',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active',
        ]);

        return Caregiver::create([
            'user_id' => $user->id, 'code' => $code, 'status' => 'active',
            'right_to_work_verified' => $placeable, 'police_check_expires_at' => now()->addYear(),
        ]);
    }

    private function assign(Caregiver $caregiver, array $overrides = [])
    {
        return $this->actingAs($this->staff)->post(route('admin.assignments.store', $this->patient), $overrides + [
            'caregiver_id' => $caregiver->id, 'service_id' => $this->hourly->id,
            'role' => 'primary', 'start_date' => today()->toDateString(), 'status' => 'active',
        ]);
    }

    public function test_an_assignment_is_created_and_a_weekly_pattern_becomes_dated_shifts(): void
    {
        $this->assign($this->siti)->assertSessionHasNoErrors();
        $assignment = Assignment::firstOrFail();
        $this->assertEquals(35, $assignment->charge_rate, 'Defaults to the list price.');
        $this->assertNotNull($assignment->care_plan_id);

        $this->post(route('admin.shifts.generate', $assignment), [
            'weekdays' => [1, 3, 5], 'start_time' => '08:00', 'end_time' => '13:00',
            'from' => '2026-10-05', 'until' => '2026-10-18',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['2026-10-05', '2026-10-07', '2026-10-09', '2026-10-12', '2026-10-14', '2026-10-16'],
            $assignment->shifts()->orderBy('shift_date')->get()->map(fn ($s) => $s->shift_date->toDateString())->all()
        );
        $this->assertSame('08:00:00', Shift::first()->start_time);

        // Running it again books nothing twice.
        $this->post(route('admin.shifts.generate', $assignment), [
            'weekdays' => [1, 3, 5], 'start_time' => '08:00', 'end_time' => '13:00',
            'from' => '2026-10-05', 'until' => '2026-10-18',
        ]);
        $this->assertSame(6, $assignment->shifts()->count());
    }

    public function test_a_caregiver_cannot_be_booked_in_two_homes_at_once(): void
    {
        $this->assign($this->siti);
        $first = Assignment::firstOrFail();
        $this->post(route('admin.shifts.generate', $first), [
            'weekdays' => [1], 'start_time' => '08:00', 'end_time' => '13:00', 'from' => '2026-10-05', 'until' => '2026-10-05',
        ]);

        $other = Patient::create(['code' => 'SCP-0002', 'name' => 'Mr Tan', 'status' => 'active']);
        $otherPlan = $other->carePlans()->create(['version' => 1, 'effective_from' => today(), 'status' => 'active']);
        $this->post(route('admin.assignments.store', $other), [
            'caregiver_id' => $this->siti->id, 'service_id' => $this->hourly->id,
            'role' => 'primary', 'start_date' => today()->toDateString(), 'status' => 'active',
        ]);
        $second = Assignment::where('patient_id', $other->id)->firstOrFail();

        // 12:00–15:00 overlaps 08:00–13:00 on Monday; Tuesday is free.
        $this->post(route('admin.shifts.generate', $second), [
            'weekdays' => [1, 2], 'start_time' => '12:00', 'end_time' => '15:00', 'from' => '2026-10-05', 'until' => '2026-10-06',
        ])->assertSessionHas('status', fn ($m) => str_contains($m, 'NOT booked') && str_contains($m, 'Mon 5 Oct'));

        $this->assertSame(['2026-10-06'], $second->shifts->map(fn ($s) => $s->shift_date->toDateString())->all());

        // Back to back is fine.
        $this->post(route('admin.shifts.store', $second), ['shift_date' => '2026-10-05', 'start_time' => '13:00', 'end_time' => '15:00'])
            ->assertSessionHasNoErrors();
    }

    public function test_ongoing_care_needs_an_agreed_plan_and_a_placeable_caregiver(): void
    {
        $this->patient->carePlans()->update(['status' => 'superseded']);
        $this->assign($this->siti)->assertSessionHasErrors('service_id');

        // A massage needs no care plan.
        $this->assign($this->siti, ['service_id' => $this->massage->id])->assertSessionHasNoErrors();

        $unvetted = $this->caregiver('Kavitha', 'CG-003', placeable: false);
        $this->assign($unvetted, ['service_id' => $this->massage->id])->assertSessionHasErrors('caregiver_id');
    }

    public function test_a_one_off_massage_is_one_assignment_with_one_shift(): void
    {
        $this->actingAs($this->staff)->post(route('admin.assignments.store', $this->patient), [
            'one_off' => 1, 'caregiver_id' => $this->nurul->id, 'service_id' => $this->massage->id,
            'date' => '2026-10-07', 'start_time' => '15:00', 'end_time' => '16:00',
        ])->assertSessionHasNoErrors();

        $assignment = Assignment::firstOrFail();
        $this->assertTrue($assignment->isOneOff());
        $this->assertSame(1, $assignment->shifts()->count());
        $this->assertEquals(120, $assignment->rate());
    }

    public function test_cancel_cover_and_move_a_single_shift(): void
    {
        $this->assign($this->siti);
        $assignment = Assignment::firstOrFail();
        $this->post(route('admin.shifts.generate', $assignment), [
            'weekdays' => [1, 2], 'start_time' => '08:00', 'end_time' => '13:00', 'from' => '2026-10-05', 'until' => '2026-10-06',
        ]);
        [$mon, $tue] = $assignment->shifts()->orderBy('shift_date')->get();

        $this->post(route('admin.shifts.cancel', $mon), [])->assertSessionHasErrors('cancel_reason');
        $this->post(route('admin.shifts.cancel', $mon), ['cancel_reason' => 'Hospital appointment']);
        $this->assertSame('cancelled', $mon->fresh()->status);

        $this->post(route('admin.shifts.cover', $tue), ['covered_by_id' => $this->nurul->id])->assertSessionHasNoErrors();
        $this->assertSame($this->nurul->id, $tue->fresh()->caregiverId());
        $this->assertSame([$tue->id], Shift::workedBy($this->nurul->id)->pluck('id')->all());
        $this->assertSame([$mon->id], Shift::workedBy($this->siti->id)->pluck('id')->all());

        $this->put(route('admin.shifts.update', $tue), ['shift_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '14:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame('09:00–14:00', $tue->fresh()->timeRange());
    }

    public function test_ending_an_assignment_cancels_later_shifts_and_keeps_earlier_ones(): void
    {
        $this->assign($this->siti);
        $assignment = Assignment::firstOrFail();
        $this->post(route('admin.shifts.generate', $assignment), [
            'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '08:00', 'end_time' => '13:00', 'from' => '2026-10-05', 'until' => '2026-10-09',
        ]);

        $this->post(route('admin.assignments.end', $assignment), ['last_day' => '2026-10-07', 'reason' => 'Moved to a care home'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ended', $assignment->fresh()->status);
        $this->assertSame(3, $assignment->shifts()->where('status', 'scheduled')->count());
        $this->assertSame(2, $assignment->shifts()->where('status', 'cancelled')->count());
    }

    public function test_pages_render(): void
    {
        $this->assign($this->siti);
        $assignment = Assignment::firstOrFail();
        $this->post(route('admin.shifts.generate', $assignment), [
            'weekdays' => [1], 'start_time' => '08:00', 'end_time' => '13:00', 'from' => '2026-10-05', 'until' => '2026-10-05',
        ]);

        $this->get(route('admin.assignments.create', $this->patient))->assertOk();
        $this->get(route('admin.assignments.create', [$this->patient, 'type' => 'visit']))->assertOk()->assertSee('Book a one-off visit');
        $this->get(route('admin.assignments.show', $assignment))->assertOk()->assertSee('Book shifts');
        $this->get(route('admin.shifts.show', Shift::first()))->assertOk()->assertSee('Relief cover');
        $this->get(route('admin.schedule'))->assertOk()->assertSee('Puan Aminah')->assertSee('08:00–13:00');
        $this->get(route('admin.schedule', ['caregiver' => $this->nurul->id]))->assertOk()->assertDontSee('08:00–13:00');
        $this->get(route('admin.patients.show', $this->patient))->assertOk()->assertSee('Personal care');
        $this->get(route('admin.caregivers.show', $this->siti))->assertOk();
    }
}
