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

class FamilyApiTest extends TestCase
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

        $token = $this->postJson('/api/v1/login', ['email' => 'h@example.test', 'password' => 'password'])->json('token');
        $this->withHeader('Authorization', "Bearer {$token}");

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
        $this->getJson('/api/v1/family/clients')->assertOk()
            ->assertJsonCount(1, 'clients')->assertJsonPath('clients.0.relationship', 'Daughter');

        $this->getJson("/api/v1/family/clients/{$this->aminah->id}")->assertOk()
            ->assertJsonPath('coming.0.date', '2026-10-08')->assertJsonPath('coming.0.caregiver', 'Siti Nurhaliza')
            ->assertJsonCount(1, 'coming')
            ->assertJsonPath('visits.0.done.0', 'Bed bath')->assertJsonPath('visits.0.notes', null)
            ->assertJsonPath('invoices', [])->assertJsonPath('bank', null)
            ->assertJsonPath('categories.0.value', 'plan_change')
            ->assertDontSee('low in mood');

        $this->link->update(['can_view_notes' => true]);
        $this->getJson("/api/v1/family/clients/{$this->aminah->id}")
            ->assertJsonPath('visits.0.notes', 'Private: a little low in mood today.');
    }

    public function test_family_cannot_see_anyone_else_or_the_office(): void
    {
        $this->getJson("/api/v1/family/clients/{$this->stranger->id}")->assertNotFound();
        $this->postJson("/api/v1/family/clients/{$this->stranger->id}/concerns", ['category' => 'other', 'detail' => 'x'])->assertNotFound();
        $this->getJson('/api/v1/office/day')->assertForbidden();
        $this->getJson('/api/v1/shifts')->assertForbidden();
        $this->getJson('/api/v1/my-care')->assertForbidden();
    }

    public function test_invoices_only_when_allowed_and_never_drafts(): void
    {
        $issued = Invoice::create(['number' => 'INV-2026-0001', 'patient_id' => $this->aminah->id, 'period_start' => '2026-09-01',
            'period_end' => '2026-09-30', 'total' => 100, 'status' => 'sent', 'issued_at' => now(), 'due_date' => now()->addDays(14)]);
        $draft = Invoice::create(['number' => 'DRAFT-9', 'patient_id' => $this->aminah->id, 'period_start' => '2026-10-01',
            'period_end' => '2026-10-31', 'total' => 50, 'status' => 'draft']);

        $this->getJson("/api/v1/family/invoices/{$issued->id}/html")->assertNotFound();

        $this->link->update(['can_view_invoices' => true]);
        $this->getJson("/api/v1/family/clients/{$this->aminah->id}")
            ->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.number', 'INV-2026-0001');
        $this->getJson("/api/v1/family/invoices/{$issued->id}/html")->assertOk()
            ->assertJsonPath('filename', 'INV-2026-0001.pdf')->assertSee('RM 100.00');
        $this->getJson("/api/v1/family/invoices/{$draft->id}/html")->assertNotFound();
    }

    public function test_concerns_and_change_requests_reach_the_office_inbox(): void
    {
        $this->postJson("/api/v1/family/clients/{$this->aminah->id}/concerns", [
            'category' => 'plan_change', 'detail' => 'Could the visits start at 9 instead?',
        ])->assertCreated();
        $this->assertStringStartsWith('Care plan change requested', Concern::firstOrFail()->detail);

        $this->link->update(['can_request_changes' => false]);
        $this->postJson("/api/v1/family/clients/{$this->aminah->id}/concerns", ['category' => 'plan_change', 'detail' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('category');
        $this->postJson("/api/v1/family/clients/{$this->aminah->id}/concerns", ['category' => 'attendance', 'detail' => 'Late on Monday'])
            ->assertCreated();

        $this->getJson("/api/v1/family/clients/{$this->aminah->id}")
            ->assertJsonCount(2, 'concerns')->assertJsonPath('concerns.0.open', true)
            ->assertJsonPath('concerns.1.category', 'A change to the care plan');
    }

    public function test_a_client_gets_their_own_small_view(): void
    {
        $client = User::create(['name' => 'Aminah', 'email' => 'aminah@example.test', 'password' => 'password',
            'role' => User::ROLE_PATIENT, 'status' => 'active']);
        $this->aminah->update(['user_id' => $client->id]);

        $token = $this->postJson('/api/v1/login', ['email' => 'aminah@example.test', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role', 'patient')->json('token');
        $this->withHeader('Authorization', "Bearer {$token}");

        $this->getJson('/api/v1/my-care')->assertOk()
            ->assertJsonPath('name', 'Puan Aminah')->assertJsonPath('coming.0.caregiver', 'Siti Nurhaliza')
            ->assertDontSee('low in mood');
        $this->postJson('/api/v1/my-care/concerns', ['category' => 'care_quality', 'detail' => 'The tea was cold'])->assertCreated();
        $this->postJson('/api/v1/my-care/concerns', ['category' => 'plan_change', 'detail' => 'x'])->assertStatus(422);
        $this->getJson("/api/v1/family/clients/{$this->aminah->id}")->assertForbidden();
    }
}
