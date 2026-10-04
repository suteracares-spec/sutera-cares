<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    private User $coord;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 10:00');

        $this->coord = User::create(['name' => 'Coord', 'email' => 'coord@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);
        $token = $this->postJson('/api/v1/login', ['email' => 'coord@example.test', 'password' => 'correct horse battery'])->json('token');
        $this->withHeader('Authorization', "Bearer {$token}");
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function newClient(array $overrides = []): int
    {
        return $this->postJson('/api/v1/office/clients', $overrides + [
            'name' => 'Puan Aminah', 'area' => 'Cheras', 'status' => 'assessment', 'ic_number' => '450101-10-1234',
        ])->assertCreated()->json('id');
    }

    public function test_clients_are_created_searched_viewed_edited_and_archived(): void
    {
        $id = $this->newClient();
        $this->assertSame('SCP-0001', Patient::find($id)->code);

        $this->getJson('/api/v1/office/clients?q=Cheras')->assertOk()->assertJsonPath('clients.0.name', 'Puan Aminah')
            ->assertJsonPath('clients.0.consent', false);
        $this->getJson('/api/v1/office/clients?q=Penang')->assertOk()->assertJsonCount(0, 'clients');

        $this->getJson("/api/v1/office/clients/{$id}")->assertOk()->assertJsonPath('ic_number', '450101-10-1234');
        $this->assertTrue(AuditLog::where(['action' => 'viewed', 'subject_type' => 'patient', 'subject_id' => $id])->exists());

        $this->putJson("/api/v1/office/clients/{$id}", ['name' => 'Puan Aminah binti Hassan', 'status' => 'active',
            'consent_given_at' => '2026-10-05', 'consent_by' => 'Daughter'])
            ->assertOk()->assertJsonPath('consent_by', 'Daughter')->assertJsonPath('status', 'active');

        $this->putJson("/api/v1/office/clients/{$id}", ['name' => '', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->deleteJson("/api/v1/office/clients/{$id}")->assertOk();
        $this->assertSoftDeleted('patients', ['id' => $id]);
    }

    public function test_a_care_plan_is_drafted_refuses_clinical_tasks_and_activates_only_with_consent(): void
    {
        $id = $this->newClient();

        $plan = $this->postJson("/api/v1/office/clients/{$id}/care-plans")->assertOk()
            ->assertJsonPath('status', 'draft')->assertJsonPath('version', 1)->json('id');

        $this->putJson("/api/v1/office/care-plans/{$plan}", ['effective_from' => '2026-10-05',
            'tasks' => [['category' => 'personal_care', 'description' => 'Give insulin injection']]])
            ->assertStatus(422)->assertJsonValidationErrors('tasks.0.description');

        $this->putJson("/api/v1/office/care-plans/{$plan}", ['effective_from' => '2026-10-05', 'notes' => 'Tea first',
            'tasks' => [['category' => 'personal_care', 'description' => 'Bed bath', 'frequency' => 'every_visit', 'time_of_day' => 'morning']]])
            ->assertOk()->assertJsonPath('tasks.0.description', 'Bed bath');

        // No consent yet.
        $this->postJson("/api/v1/office/care-plans/{$plan}/activate", ['agreed_by' => 'Daughter', 'agreed_at' => '2026-10-05'])
            ->assertStatus(422)->assertJsonValidationErrors('agreed_by');

        $this->putJson("/api/v1/office/clients/{$id}", ['name' => 'Puan Aminah', 'status' => 'active', 'consent_given_at' => '2026-10-05']);
        $this->postJson("/api/v1/office/care-plans/{$plan}/activate", ['agreed_by' => 'Daughter', 'agreed_at' => '2026-10-05'])
            ->assertOk()->assertJsonPath('status', 'active');

        // Agreed: no more edits; a revision copies it.
        $this->putJson("/api/v1/office/care-plans/{$plan}", ['effective_from' => '2026-10-06', 'tasks' => []])->assertStatus(422);
        $this->postJson("/api/v1/office/clients/{$id}/care-plans")->assertOk()
            ->assertJsonPath('version', 2)->assertJsonPath('tasks.0.description', 'Bed bath');
    }

    public function test_family_access_and_temporary_passwords(): void
    {
        $id = $this->newClient();

        $this->postJson("/api/v1/office/clients/{$id}/family", ['name' => 'Nor Hayati', 'email' => 'hayati@example.test',
            'relationship' => 'Daughter', 'is_primary' => true])
            ->assertOk()->assertJsonPath('family.0.name', 'Nor Hayati')->assertJsonPath('family.0.can_view_notes', false);

        $link = Patient::find($id)->guardians()->firstOrFail();
        $this->putJson("/api/v1/office/family/{$link->id}", ['name' => 'Nor Hayati', 'email' => 'hayati@example.test',
            'is_primary' => true, 'can_view_notes' => true])->assertOk()->assertJsonPath('family.0.can_view_notes', true);

        $issued = $this->postJson("/api/v1/office/users/{$link->user_id}/temporary-password")->assertOk()
            ->json('credentials');
        $this->assertSame('hayati@example.test', $issued['email']);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $issued['password']);

        $this->deleteJson("/api/v1/office/family/{$link->id}")->assertOk()->assertJsonCount(0, 'family');
        $this->assertSame('suspended', User::find($link->user_id)->status);

        // A client's own sign-in.
        $this->postJson("/api/v1/office/clients/{$id}/sign-in", ['email' => 'aminah@example.test'])->assertOk()
            ->assertJsonPath('credentials.email', 'aminah@example.test');
        $this->postJson("/api/v1/office/clients/{$id}/sign-in", ['email' => 'again@example.test'])->assertStatus(422);
    }

    public function test_a_coordinator_cannot_reset_staff_from_the_app(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x',
            'role' => User::ROLE_ADMIN, 'status' => 'active']);

        $this->postJson("/api/v1/office/users/{$admin->id}/temporary-password")->assertForbidden();
        $this->postJson("/api/v1/office/users/{$this->coord->id}/temporary-password")->assertForbidden();
    }

    public function test_options_describe_the_forms(): void
    {
        $this->getJson('/api/v1/office/client-options')->assertOk()
            ->assertJsonPath('categories.personal_care', 'Personal care')
            ->assertJsonPath('statuses.0', 'enquiry');
    }

    public function test_an_office_temp_password_reset_signs_the_phone_out(): void
    {
        $carer = User::create(['name' => 'Siti', 'email' => 'siti@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active']);
        ApiToken::issue($carer, 'phone');

        $this->postJson("/api/v1/office/users/{$carer->id}/temporary-password")->assertOk();
        $this->assertSame(0, ApiToken::where('user_id', $carer->id)->count());
    }
}
