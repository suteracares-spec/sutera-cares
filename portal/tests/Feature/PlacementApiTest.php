<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Caregiver;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlacementApiTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    private Service $hourly;

    private Service $massage;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 09:00');   // a Monday

        User::create(['name' => 'Coord', 'email' => 'coord@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);
        $token = $this->postJson('/api/v1/login', ['email' => 'coord@example.test', 'password' => 'correct horse battery'])->json('token');
        $this->withHeader('Authorization', "Bearer {$token}");

        $this->patient = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active', 'consent_given_at' => now()]);
        $this->hourly = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $this->massage = Service::create(['code' => 'MASSAGE-60', 'name' => 'Massage', 'category' => 'wellness', 'unit' => 'session', 'base_rate' => 120]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function newCaregiver(array $overrides = []): int
    {
        return $this->postJson('/api/v1/office/caregivers', $overrides + [
            'name' => 'Siti Nurhaliza', 'email' => 'siti@example.test', 'status' => 'active',
            'right_to_work_verified' => true, 'police_check_expires_at' => '2027-06-01', 'base_area' => 'Cheras',
        ])->assertCreated()->json('id');
    }

    public function test_caregivers_are_created_listed_edited_and_archived(): void
    {
        $id = $this->newCaregiver();
        $this->assertSame('invited', Caregiver::find($id)->user->status);

        $this->getJson('/api/v1/office/caregivers?q=Cheras')->assertOk()
            ->assertJsonPath('caregivers.0.name', 'Siti Nurhaliza')->assertJsonPath('caregivers.0.placeable', true);

        // A police check lapsing soon is flagged.
        $this->putJson("/api/v1/office/caregivers/{$id}", ['name' => 'Siti Nurhaliza', 'email' => 'siti@example.test',
            'status' => 'active', 'right_to_work_verified' => true, 'police_check_expires_at' => '2026-11-01'])
            ->assertOk()->assertJsonPath('check_expiring', true)->assertJsonPath('login_status', 'invited');

        $this->postJson('/api/v1/office/caregivers', ['name' => 'Dup', 'email' => 'siti@example.test', 'status' => 'applicant'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->deleteJson("/api/v1/office/caregivers/{$id}")->assertOk();
        $this->assertSoftDeleted('caregivers', ['id' => $id]);
    }

    public function test_assigning_needs_vetting_and_a_care_plan_for_ongoing_care(): void
    {
        $siti = $this->newCaregiver();
        $unvetted = $this->newCaregiver(['name' => 'Kavitha', 'email' => 'k@example.test', 'right_to_work_verified' => false]);

        $this->getJson("/api/v1/office/placement-options?client={$this->patient->id}")->assertOk()
            ->assertJsonCount(1, 'caregivers')->assertJsonPath('client_has_care_plan', false);

        $this->postJson("/api/v1/office/clients/{$this->patient->id}/assignments", [
            'caregiver_id' => $siti, 'service_id' => $this->hourly->id, 'role' => 'primary', 'start_date' => '2026-10-05', 'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('service_id');

        $this->postJson("/api/v1/office/clients/{$this->patient->id}/assignments", [
            'one_off' => true, 'caregiver_id' => $unvetted, 'service_id' => $this->massage->id,
            'date' => '2026-10-07', 'start_time' => '15:00', 'end_time' => '16:00',
        ])->assertStatus(422)->assertJsonValidationErrors('caregiver_id');
    }

    public function test_a_placement_gets_a_weekly_pattern_a_single_shift_and_is_ended(): void
    {
        $siti = $this->newCaregiver();
        $this->patient->carePlans()->create(['version' => 1, 'effective_from' => today(), 'status' => 'active']);

        $id = $this->postJson("/api/v1/office/clients/{$this->patient->id}/assignments", [
            'caregiver_id' => $siti, 'service_id' => $this->hourly->id, 'role' => 'primary',
            'start_date' => '2026-10-05', 'status' => 'active', 'charge_rate' => 30,
        ])->assertCreated()->assertJsonPath('charge_rate', 30)->json('id');

        $this->postJson("/api/v1/office/assignments/{$id}/shifts/generate", [
            'weekdays' => [1, 3, 5], 'start_time' => '08:00', 'end_time' => '13:00', 'from' => '2026-10-05', 'until' => '2026-10-18',
        ])->assertOk()->assertJsonPath('message', '6 shifts booked.')->assertJsonCount(6, 'upcoming');

        $this->postJson("/api/v1/office/assignments/{$id}/shifts", ['shift_date' => '2026-10-06', 'start_time' => '14:00', 'end_time' => '16:00'])
            ->assertOk()->assertJsonCount(7, 'upcoming');

        // Clash with her own morning shift.
        $this->postJson("/api/v1/office/assignments/{$id}/shifts", ['shift_date' => '2026-10-07', 'start_time' => '12:00', 'end_time' => '14:00'])
            ->assertStatus(422)->assertJsonValidationErrors('start_time');

        $this->postJson("/api/v1/office/assignments/{$id}/end", ['last_day' => '2026-10-09', 'reason' => 'Moved to a care home'])
            ->assertOk()->assertJsonPath('status', 'ended');
        // Mon 12, Wed 14 and Fri 16 come after the last day.
        $this->assertSame(3, Assignment::find($id)->shifts()->where('status', 'cancelled')->count());
    }

    public function test_a_one_off_massage_is_booked_in_one_step(): void
    {
        $siti = $this->newCaregiver();

        $this->postJson("/api/v1/office/clients/{$this->patient->id}/assignments", [
            'one_off' => true, 'caregiver_id' => $siti, 'service_id' => $this->massage->id,
            'date' => '2026-10-07', 'start_time' => '15:00', 'end_time' => '16:00',
        ])->assertCreated()->assertJsonPath('one_off', true)->assertJsonPath('message', 'Visit booked.')
            ->assertJsonCount(1, 'upcoming')->assertJsonPath('charge_rate', 120);
    }
}
