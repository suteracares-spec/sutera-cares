<?php

namespace Tests\Feature;

use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FamilyPortalTest extends TestCase
{
    use RefreshDatabase;

    private Patient $aminah;

    private Patient $stranger;

    private User $daughter;

    private Guardian $link;

    private Shift $past;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00');

        $this->aminah = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active']);
        $this->stranger = Patient::create(['code' => 'SCP-0002', 'name' => 'Mr Tan', 'status' => 'active']);

        $this->daughter = User::create(['name' => 'Nor Hayati', 'email' => 'h@example.test', 'password' => 'password',
            'role' => User::ROLE_GUARDIAN, 'status' => 'active']);
        $this->link = $this->aminah->guardians()->create(['user_id' => $this->daughter->id, 'relationship' => 'Daughter',
            'can_view_notes' => false, 'can_view_invoices' => false, 'can_request_changes' => true]);

        $cg = User::create(['name' => 'Siti Nurhaliza', 'email' => 's@example.test', 'password' => 'password',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active']);
        $siti = Caregiver::create(['user_id' => $cg->id, 'code' => 'CG-001', 'status' => 'active']);
        $service = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $assignment = $this->aminah->assignments()->create(['caregiver_id' => $siti->id, 'service_id' => $service->id,
            'start_date' => '2026-10-01', 'status' => 'active']);

        $this->past = $assignment->shifts()->create(['shift_date' => '2026-10-06', 'start_time' => '08:00', 'end_time' => '13:00', 'status' => 'completed']);
        $this->past->visitLog()->create(['caregiver_id' => $siti->id, 'check_in_at' => '2026-10-06 08:02', 'check_out_at' => '2026-10-06 13:00',
            'tasks_completed' => ['Bed bath'], 'notes' => 'Private: a little low in mood today.']);
        $assignment->shifts()->create(['shift_date' => '2026-10-08', 'start_time' => '08:00', 'end_time' => '13:00']);

        $this->stranger->assignments()->create(['caregiver_id' => $siti->id, 'service_id' => $service->id, 'start_date' => '2026-10-01', 'status' => 'active'])
            ->shifts()->create(['shift_date' => '2026-10-08', 'start_time' => '15:00', 'end_time' => '17:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_family_sees_who_is_coming_and_what_happened_but_notes_only_when_allowed(): void
    {
        $this->actingAs($this->daughter)->get(route('guardian.dashboard'))
            ->assertRedirect(route('guardian.clients.show', $this->aminah));

        $this->get(route('guardian.clients.show', $this->aminah))->assertOk()
            ->assertSee('Tomorrow')->assertSee('Siti Nurhaliza')
            ->assertSee('Arrived 08:02')->assertSee('Bed bath')
            ->assertDontSee('low in mood')->assertDontSee('15:00');

        $this->link->update(['can_view_notes' => true]);
        $this->get(route('guardian.clients.show', $this->aminah))->assertSee('low in mood');
    }

    public function test_family_cannot_see_anyone_else(): void
    {
        $this->actingAs($this->daughter)->get(route('guardian.clients.show', $this->stranger))->assertNotFound();
        $this->post(route('guardian.clients.concern', $this->stranger), ['category' => 'other', 'detail' => 'x'])->assertNotFound();
        $this->get(route('caregiver.dashboard'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_invoices_only_when_allowed_and_never_drafts(): void
    {
        $issued = Invoice::create(['number' => 'INV-2026-0001', 'patient_id' => $this->aminah->id, 'period_start' => '2026-09-01',
            'period_end' => '2026-09-30', 'total' => 100, 'status' => 'sent', 'issued_at' => now(), 'due_date' => now()->addDays(14)]);
        $draft = Invoice::create(['number' => 'DRAFT-9', 'patient_id' => $this->aminah->id, 'period_start' => '2026-10-01',
            'period_end' => '2026-10-31', 'total' => 50, 'status' => 'draft']);

        $this->actingAs($this->daughter)->get(route('guardian.invoices.show', $issued))->assertNotFound();
        $this->get(route('guardian.clients.show', $this->aminah))->assertDontSee('INV-2026-0001');

        $this->link->update(['can_view_invoices' => true]);
        $this->get(route('guardian.clients.show', $this->aminah))->assertSee('INV-2026-0001')->assertDontSee('DRAFT-9');
        $this->get(route('guardian.invoices.show', $issued))->assertOk()->assertSee('RM 100.00');
        $this->get(route('guardian.invoices.show', $draft))->assertNotFound();
    }

    public function test_concerns_and_change_requests_reach_the_office_inbox(): void
    {
        $this->actingAs($this->daughter)->post(route('guardian.clients.concern', $this->aminah), [
            'category' => 'plan_change', 'detail' => 'Could the visits start at 9 instead?',
        ])->assertSessionHasNoErrors();

        $concern = Concern::firstOrFail();
        $this->assertStringStartsWith('Care plan change requested', $concern->detail);
        $this->assertSame($this->aminah->id, $concern->patient_id);

        // Without the switch, change requests are refused; concerns are not.
        $this->link->update(['can_request_changes' => false]);
        $this->post(route('guardian.clients.concern', $this->aminah), ['category' => 'plan_change', 'detail' => 'x'])
            ->assertSessionHasErrors('category');
        $this->post(route('guardian.clients.concern', $this->aminah), ['category' => 'attendance', 'detail' => 'Late on Monday'])
            ->assertSessionHasNoErrors();

        $staff = User::create(['name' => 'Coord', 'email' => 'c@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);
        $this->actingAs($staff)->get(route('admin.concerns.index'))->assertOk()->assertSee('Care plan change')->assertSee('Nor Hayati');
        $this->get(route('admin.concerns.show', $concern))->assertOk()->assertSee('start at 9');

        $this->put(route('admin.concerns.update', $concern), ['status' => 'resolved', 'assigned_to_id' => $staff->id])
            ->assertSessionHasErrors('resolution');
        $this->put(route('admin.concerns.update', $concern), ['status' => 'resolved', 'assigned_to_id' => $staff->id,
            'resolution' => 'Moved to 9am from next week.'])->assertSessionHasNoErrors();
        $this->assertNotNull($concern->fresh()->resolved_at);

        $this->actingAs($this->daughter)->get(route('guardian.clients.show', $this->aminah))->assertSee('Resolved')->assertDontSee('Moved to 9am');
    }

    public function test_a_client_gets_their_own_small_view(): void
    {
        $staff = User::create(['name' => 'Coord', 'email' => 'c@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);

        $this->actingAs($staff)->post(route('admin.patients.sign-in', $this->aminah), ['email' => 'aminah@example.test'])
            ->assertSessionHas('temp_password');
        $client = User::where('email', 'aminah@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_PATIENT, $client->role);
        $this->assertSame($client->id, $this->aminah->fresh()->user_id);

        $client->update(['status' => 'active']);
        $this->actingAs($client)->get(route('patient.dashboard'))->assertOk()->assertSee('Siti Nurhaliza')->assertDontSee('low in mood');
        $this->post(route('patient.concern'), ['category' => 'care_quality', 'detail' => 'The tea was cold'])->assertSessionHasNoErrors();
        $this->assertSame(1, Concern::where('raised_by_id', $client->id)->count());
    }
}
