<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaregiverApiTest extends TestCase
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
        Http::fake();

        $this->siti = $this->caregiver('Siti', 'CG-001');
        $this->nurul = $this->caregiver('Nurul', 'CG-002');

        $this->patient = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active',
            'address' => '12 Jalan Mawar', 'allergies' => 'Penicillin', 'ic_number' => '450101-10-1234', 'notes' => 'Office-only note']);
        $plan = $this->patient->carePlans()->create(['version' => 1, 'effective_from' => today(), 'status' => 'active',
            'notes' => 'Likes tea first']);
        $plan->tasks()->create(['category' => 'personal_care', 'description' => 'Bed bath']);

        $service = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $this->shift = $this->patient->assignments()->create(['caregiver_id' => $this->siti->id, 'service_id' => $service->id,
            'start_date' => today(), 'status' => 'active'])
            ->shifts()->create(['shift_date' => today(), 'start_time' => '08:00', 'end_time' => '13:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function caregiver(string $name, string $code, string $status = 'active'): Caregiver
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name) . '@example.test',
            'password' => 'correct horse battery', 'role' => User::ROLE_CAREGIVER, 'status' => $status]);

        return Caregiver::create(['user_id' => $user->id, 'code' => $code, 'status' => 'active',
            'right_to_work_verified' => true, 'police_check_expires_at' => now()->addYear()]);
    }

    private function tokenFor(Caregiver $c): string
    {
        return $this->postJson('/api/v1/login', ['email' => $c->user->email, 'password' => 'correct horse battery', 'device' => 'Pixel 7'])
            ->assertOk()->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_sign_in_issues_a_hashed_token_and_refuses_bad_details_and_non_caregivers(): void
    {
        $token = $this->tokenFor($this->siti);
        $this->assertStringStartsWith('sutera_', $token);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('api_tokens', ['token_hash' => hash('sha256', $token), 'device' => 'Pixel 7']);

        $this->postJson('/api/v1/login', ['email' => 'siti@example.test', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Those details do not match our records.');

        $family = User::create(['name' => 'Fam', 'email' => 'fam@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_GUARDIAN, 'status' => 'active']);
        $familyToken = $this->postJson('/api/v1/login', ['email' => $family->email, 'password' => 'correct horse battery'])
            ->assertOk()->assertJsonPath('user.role', 'guardian')->json('token');
        // A family account has no shifts to work.
        $this->withHeader('Authorization', "Bearer {$familyToken}")->getJson('/api/v1/shifts')->assertForbidden();
        $this->withHeader('Authorization', '');

        $this->getJson('/api/v1/shifts')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer nonsense')->getJson('/api/v1/shifts')->assertUnauthorized();
    }

    public function test_a_temporary_password_must_be_replaced_before_anything_else(): void
    {
        $new = $this->caregiver('Kavitha', 'CG-003', 'invited');
        $token = $this->postJson('/api/v1/login', ['email' => 'kavitha@example.test', 'password' => 'correct horse battery'])
            ->assertOk()->assertJsonPath('password_change_required', true)->json('token');

        $this->api($token)->getJson('/api/v1/shifts')->assertForbidden()->assertJsonPath('password_change_required', true);

        $this->api($token)->postJson('/api/v1/password', ['password' => 'mango lantern river seven',
            'password_confirmation' => 'mango lantern river seven'])->assertOk()->assertJsonPath('password_change_required', false);

        $this->assertSame('active', $new->user->fresh()->status);
        $this->api($token)->getJson('/api/v1/shifts')->assertOk();
    }

    public function test_shifts_list_and_detail_show_only_what_the_visit_needs(): void
    {
        $token = $this->tokenFor($this->siti);

        $this->api($token)->getJson('/api/v1/shifts')->assertOk()
            ->assertJsonPath('today.0.client.name', 'Puan Aminah')
            ->assertJsonPath('today.0.start', '08:00');

        $this->api($token)->getJson("/api/v1/shifts/{$this->shift->id}")->assertOk()
            ->assertJsonPath('client_details.allergies', 'Penicillin')
            ->assertJsonPath('plan.notes', 'Likes tea first')
            ->assertJsonPath('plan.tasks.0.description', 'Bed bath')
            ->assertDontSee('450101-10-1234')->assertDontSee('Office-only note');

        $other = $this->tokenFor($this->nurul);
        $this->api($other)->getJson("/api/v1/shifts/{$this->shift->id}")->assertNotFound();
        $this->api($other)->postJson("/api/v1/shifts/{$this->shift->id}/check-in")->assertNotFound();
    }

    public function test_an_offline_check_in_keeps_the_tapped_time_and_retries_are_harmless(): void
    {
        $token = $this->tokenFor($this->siti);

        // Tapped at 08:02 in a lift with no signal; it reaches the server at 08:40.
        Carbon::setTestNow('2026-10-05 08:40');
        $payload = ['at' => '2026-10-05T08:02:00+08:00', 'lat' => 3.07, 'lng' => 101.52];

        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-in", $payload)
            ->assertOk()->assertJsonPath('status', 'in_progress');
        $this->assertSame('08:02', $this->shift->fresh()->visitLog->check_in_at->format('H:i'));

        // The phone was not sure it got through, and sends it again.
        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-in", $payload)
            ->assertOk()->assertJsonPath('already', true);
        $this->assertSame(1, \App\Models\VisitLog::count());

        Carbon::setTestNow('2026-10-05 13:05');
        $bath = $this->patient->activeCarePlan()->tasks()->value('id');
        $out = ['at' => '2026-10-05T13:00:00+08:00', 'tasks' => [$bath], 'notes' => 'Ate well.',
                'concern_flagged' => true, 'concern_category' => 'safety', 'concern_detail' => 'Small bruise on arm'];

        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-out", $out)
            ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('visit.tasks_completed.0', 'Bed bath');
        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-out", $out)
            ->assertOk()->assertJsonPath('already', true);

        $this->assertSame(298, $this->shift->fresh()->visitLog->minutes_worked);
        $this->assertSame(1, Concern::count());
    }

    public function test_phone_times_are_checked(): void
    {
        $token = $this->tokenFor($this->siti);
        Carbon::setTestNow('2026-10-05 08:10');

        // Clock far ahead, or a queued action from yesterday: refused.
        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-in", ['at' => '2026-10-05T09:30:00+08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('at');
        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-in", ['at' => '2026-10-04T06:00:00+08:00'])
            ->assertStatus(422);

        // Before the window opens, even if sent late.
        $this->api($token)->postJson("/api/v1/shifts/{$this->shift->id}/check-in", ['at' => '2026-10-05T06:30:00+08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('check_in');
        $this->assertNull($this->shift->fresh()->visitLog);
    }

    public function test_tokens_are_revoked_by_sign_out_suspension_and_an_office_password_reset(): void
    {
        $token = $this->tokenFor($this->siti);
        $this->api($token)->postJson('/api/v1/logout')->assertOk();
        $this->api($token)->getJson('/api/v1/me')->assertUnauthorized();

        $token = $this->tokenFor($this->siti);
        $this->siti->user->update(['status' => 'suspended']);
        $this->api($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->siti->user->update(['status' => 'active']);

        $admin = User::create(['name' => 'Admin', 'email' => 'a@example.test', 'password' => 'x',
            'role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->actingAs($admin)->post(route('admin.users.temp-password', $this->siti->user));
        $this->assertSame(0, ApiToken::where('user_id', $this->siti->user_id)->count());
    }

    public function test_tokens_expire(): void
    {
        $token = $this->tokenFor($this->siti);
        Carbon::setTestNow(now()->addDays(ApiToken::LIFETIME_DAYS + 1));
        $this->api($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
