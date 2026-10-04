<?php

namespace Tests\Feature;

use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OfficeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Caregiver $siti;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 10:00');

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_ADMIN, 'status' => 'active']);

        $cg = User::create(['name' => 'Siti', 'email' => 'siti@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active']);
        $this->siti = Caregiver::create(['user_id' => $cg->id, 'code' => 'CG-001', 'status' => 'active']);

        $patient = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active', 'area' => 'Cheras']);
        $service = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $a = $patient->assignments()->create(['caregiver_id' => $this->siti->id, 'service_id' => $service->id, 'start_date' => today(), 'status' => 'active']);

        // 06:00–08:00 nobody came; 09:30–12:00 not checked in yet (late); 09:50 checked in; 14:00 later today.
        $a->shifts()->create(['shift_date' => today(), 'start_time' => '06:00', 'end_time' => '08:00']);
        $a->shifts()->create(['shift_date' => today(), 'start_time' => '09:30', 'end_time' => '12:00']);
        $on = $a->shifts()->create(['shift_date' => today(), 'start_time' => '09:45', 'end_time' => '11:00', 'status' => 'in_progress']);
        $on->visitLog()->create(['caregiver_id' => $this->siti->id, 'check_in_at' => now()->setTime(9, 50), 'notes' => 'Arrived']);
        $a->shifts()->create(['shift_date' => today(), 'start_time' => '14:00', 'end_time' => '16:00']);

        Concern::create(['raised_by_id' => $cg->id, 'patient_id' => $patient->id, 'category' => 'safety', 'detail' => 'Bruise on arm', 'status' => 'open']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function token(string $email): string
    {
        return $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'correct horse battery'])->assertOk()->json('token');
    }

    public function test_an_administrator_signs_in_and_sees_the_day_across_everyone(): void
    {
        $login = $this->postJson('/api/v1/login', ['email' => 'admin@example.test', 'password' => 'correct horse battery'])
            ->assertOk()->assertJsonPath('user.office', true)->assertJsonPath('user.role', 'admin');
        $token = $login->json('token');

        $day = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/office/day')->assertOk();
        $day->assertJsonPath('counts.total', 4)->assertJsonPath('counts.attention', 2)->assertJsonPath('counts.on_now', 1)
            ->assertJsonPath('open_concerns', 1);

        $this->assertSame(['no_show', 'late', null, null], collect($day->json('shifts'))->pluck('attention')->all());
        $this->assertSame('Siti', $day->json('shifts.0.caregiver'));

        $id = $day->json('shifts.2.id');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/office/shifts/{$id}")
            ->assertOk()->assertJsonPath('visit.notes', 'Arrived')->assertJsonPath('client_details.code', 'SCP-0001');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/office/concerns')
            ->assertOk()->assertJsonPath('concerns.0.category', 'Safety or a fall')->assertJsonPath('concerns.0.raised_by', 'Siti');
    }

    public function test_caregivers_cannot_see_the_office_view_and_staff_have_no_caregiver_shifts(): void
    {
        $carer = $this->token('siti@example.test');
        $this->withHeader('Authorization', "Bearer {$carer}")->getJson('/api/v1/office/day')->assertForbidden();

        $office = $this->token('admin@example.test');
        $this->withHeader('Authorization', "Bearer {$office}")->getJson('/api/v1/shifts')->assertForbidden();
    }

    public function test_families_still_use_the_website(): void
    {
        User::create(['name' => 'Fam', 'email' => 'fam@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_GUARDIAN, 'status' => 'active']);

        $this->postJson('/api/v1/login', ['email' => 'fam@example.test', 'password' => 'correct horse battery'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This app is for caregivers and the office. Please use the website.');
    }

    public function test_when_two_factor_is_required_office_accounts_are_kept_to_the_website(): void
    {
        $token = $this->token('admin@example.test');

        config(['portal.require_two_factor' => true]);

        // Already-signed-in office phones stop working…
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/office/day')->assertUnauthorized();
        // …new sign-ins are refused…
        $this->postJson('/api/v1/login', ['email' => 'admin@example.test', 'password' => 'correct horse battery'])->assertStatus(422);
        // …and caregivers are unaffected.
        $this->token('siti@example.test');
    }
}
