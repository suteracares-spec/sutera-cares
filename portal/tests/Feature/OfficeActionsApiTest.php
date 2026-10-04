<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Enquiry;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OfficeActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Caregiver $siti;

    private Caregiver $nurul;

    private Shift $shift;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 07:00');   // a Monday

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->siti = $this->caregiver('Siti', 'CG-001');
        $this->nurul = $this->caregiver('Nurul', 'CG-002');

        $patient = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active']);
        $service = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $a = $patient->assignments()->create(['caregiver_id' => $this->siti->id, 'service_id' => $service->id, 'start_date' => today(), 'status' => 'active']);
        $this->shift = $a->shifts()->create(['shift_date' => today(), 'start_time' => '08:00', 'end_time' => '13:00']);
        $a->shifts()->create(['shift_date' => '2026-10-07', 'start_time' => '08:00', 'end_time' => '13:00']);

        $this->token = $this->postJson('/api/v1/login', ['email' => 'admin@example.test', 'password' => 'correct horse battery'])->json('token');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function caregiver(string $name, string $code): Caregiver
    {
        $u = User::create(['name' => $name, 'email' => strtolower($name) . '@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active']);

        return Caregiver::create(['user_id' => $u->id, 'code' => $code, 'status' => 'active',
            'right_to_work_verified' => true, 'police_check_expires_at' => now()->addYear()]);
    }

    private function api(): static
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    public function test_the_week_shows_every_day_and_filters_by_caregiver(): void
    {
        $this->api()->getJson('/api/v1/office/week?week=2026-10-07')->assertOk()
            ->assertJsonPath('week', '2026-10-05')
            ->assertJsonCount(7, 'days')
            ->assertJsonCount(2, 'shifts')
            ->assertJsonPath('shifts.0.caregiver', 'Siti');

        $this->api()->getJson("/api/v1/office/week?caregiver={$this->nurul->id}")->assertOk()->assertJsonCount(0, 'shifts');
    }

    public function test_cancel_needs_a_reason_and_is_audited(): void
    {
        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors('cancel_reason');

        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/cancel", ['cancel_reason' => 'Family away'])
            ->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('message', 'Shift on Mon 5 Oct cancelled.');

        $this->assertTrue(AuditLog::where(['action' => 'cancelled', 'subject_id' => $this->shift->id])->exists());

        // A cancelled shift can no longer be changed.
        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/missed", ['reason' => 'x'])->assertStatus(422);
    }

    public function test_relief_cover_lists_who_can_go_and_refuses_a_clash(): void
    {
        $this->api()->getJson("/api/v1/office/shifts/{$this->shift->id}/relief")->assertOk()
            ->assertJsonCount(1, 'caregivers')->assertJsonPath('caregivers.0.name', 'Nurul');

        // Nurul busy 09:00-10:00 elsewhere: refused.
        $other = Patient::create(['code' => 'SCP-0002', 'name' => 'Mr Tan', 'status' => 'active'])
            ->assignments()->create(['caregiver_id' => $this->nurul->id, 'start_date' => today(), 'status' => 'active']);
        $busy = $other->shifts()->create(['shift_date' => today(), 'start_time' => '09:00', 'end_time' => '10:00']);

        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/cover", ['covered_by_id' => $this->nurul->id])
            ->assertStatus(422)->assertJsonValidationErrors('covered_by_id');

        $busy->update(['status' => 'cancelled']);
        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/cover", ['covered_by_id' => $this->nurul->id])
            ->assertOk()->assertJsonPath('caregiver', 'Nurul')->assertJsonPath('covering', true);
    }

    public function test_move_mark_missed_and_correct_a_visit(): void
    {
        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/move", ['shift_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '12:00'])
            ->assertOk()->assertJsonPath('start', '09:00')->assertJsonPath('date', '2026-10-06');

        Carbon::setTestNow('2026-10-06 15:00');
        $this->api()->postJson("/api/v1/office/shifts/{$this->shift->id}/correct", [
            'check_in_at' => '2026-10-06T09:05:00+08:00', 'check_out_at' => '2026-10-06T12:00:00+08:00', 'reason' => 'Phone died',
        ])->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('visit.minutes_worked', 175);

        $later = Shift::whereDate('shift_date', '2026-10-07')->firstOrFail();
        Carbon::setTestNow('2026-10-07 14:00');
        $this->api()->postJson("/api/v1/office/shifts/{$later->id}/missed", ['reason' => 'Caregiver ill'])
            ->assertOk()->assertJsonPath('status', 'missed')->assertJsonPath('cancel_reason', 'Caregiver ill');
    }

    public function test_concerns_are_owned_and_resolved_with_a_record_of_what_was_done(): void
    {
        $concern = Concern::create(['raised_by_id' => $this->siti->user_id, 'category' => 'safety', 'detail' => 'Bruise', 'status' => 'open']);

        $this->api()->getJson('/api/v1/office/staff')->assertOk()->assertJsonPath('staff.0.name', 'Admin');

        $this->api()->putJson("/api/v1/office/concerns/{$concern->id}", ['status' => 'resolved', 'assigned_to_id' => $this->admin->id])
            ->assertStatus(422)->assertJsonValidationErrors('resolution');

        $this->api()->putJson("/api/v1/office/concerns/{$concern->id}", [
            'status' => 'resolved', 'assigned_to_id' => $this->admin->id, 'resolution' => 'Called the family; GP visit booked.',
        ])->assertOk()->assertJsonPath('status', 'resolved')->assertJsonPath('owner', 'Admin');
    }

    public function test_enquiries_move_through_the_funnel_and_convert_once(): void
    {
        $enquiry = Enquiry::create(['client_name' => 'Wong Mei Ling', 'client_phone' => '+60 12-345 6789',
            'patient_name' => 'Wong Fook Cheong', 'patient_area' => 'Subang Jaya', 'needs' => 'Morning bathing']);

        $this->api()->getJson('/api/v1/office/enquiries')->assertOk()->assertJsonPath('new_count', 1)
            ->assertJsonPath('enquiries.0.client_name', 'Wong Mei Ling');
        $this->api()->getJson("/api/v1/office/enquiries/{$enquiry->id}")->assertOk()->assertJsonPath('client_phone', '+60 12-345 6789');

        $this->api()->postJson("/api/v1/office/enquiries/{$enquiry->id}/status", ['status' => 'contacted'])
            ->assertOk()->assertJsonPath('status', 'contacted');

        $first = $this->api()->postJson("/api/v1/office/enquiries/{$enquiry->id}/convert")->assertOk()->json('patient_id');
        $again = $this->api()->postJson("/api/v1/office/enquiries/{$enquiry->id}/convert")->assertOk()
            ->assertJsonPath('message', 'This enquiry was already converted.')->json('patient_id');

        $this->assertSame($first, $again);
        $this->assertSame('Wong Fook Cheong', Patient::find($first)->name);
        $this->assertSame(2, Patient::count());
    }

    public function test_the_website_enquiry_actions_still_work(): void
    {
        $enquiry = Enquiry::create(['client_name' => 'Ali', 'client_phone' => '0123', 'patient_name' => 'Pak Ali']);

        $this->actingAs($this->admin)->patch(route('admin.enquiries.status', $enquiry), ['status' => 'contacted'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.enquiries.convert', $enquiry))->assertRedirect();
        $this->assertSame('converted', $enquiry->fresh()->status);
    }

    public function test_caregivers_cannot_use_office_actions(): void
    {
        $carer = $this->postJson('/api/v1/login', ['email' => 'siti@example.test', 'password' => 'correct horse battery'])->json('token');

        $this->withHeader('Authorization', "Bearer {$carer}")
            ->postJson("/api/v1/office/shifts/{$this->shift->id}/cancel", ['cancel_reason' => 'x'])->assertForbidden();
        $this->assertSame('scheduled', $this->shift->fresh()->status);
    }
}
