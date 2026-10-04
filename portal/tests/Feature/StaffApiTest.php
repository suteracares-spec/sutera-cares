<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_ADMIN, 'status' => 'active']);
    }

    private function signIn(string $email): string
    {
        return $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'correct horse battery'])->assertOk()->json('token');
    }

    public function test_an_administrator_adds_suspends_and_restores_staff(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->signIn('admin@example.test'));

        $made = $this->postJson('/api/v1/office/accounts', ['name' => 'Farah', 'email' => 'farah@example.test', 'role' => 'coordinator'])
            ->assertCreated()->assertJsonPath('credentials.email', 'farah@example.test');
        $farah = User::where('email', 'farah@example.test')->firstOrFail();
        $this->assertSame('invited', $farah->status);
        $this->assertNotEmpty($made->json('credentials.password'));

        $this->getJson('/api/v1/office/accounts')->assertOk()->assertJsonCount(2, 'staff')
            ->assertJsonPath('staff.0.is_me', true);

        // Suspending signs them out of the app too.
        $farah->forceFill(['password' => 'correct horse battery', 'status' => 'active'])->save();
        $farahToken = $this->postJson('/api/v1/login', ['email' => 'farah@example.test', 'password' => 'correct horse battery'])->json('token');
        $this->postJson("/api/v1/office/accounts/{$farah->id}/status")->assertOk()->assertJsonPath('message', 'Farah is suspended and signed out.');
        $this->assertSame(0, ApiToken::where('user_id', $farah->id)->count());
        $this->withHeader('Authorization', "Bearer {$farahToken}")->getJson('/api/v1/me')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer ' . $this->signIn('admin@example.test'));
        $this->postJson("/api/v1/office/accounts/{$farah->id}/status")->assertOk()->assertJsonPath('message', 'Farah can sign in again.');

        // Not yourself, and never the last administrator.
        $this->postJson("/api/v1/office/accounts/{$this->admin->id}/status")->assertForbidden();
    }

    public function test_two_factor_is_reset_for_a_lost_phone(): void
    {
        $farah = User::create(['name' => 'Farah', 'email' => 'farah@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);
        $farah->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();
        ApiToken::issue($farah, 'Old phone');

        $this->withHeader('Authorization', 'Bearer ' . $this->signIn('admin@example.test'));
        $this->getJson('/api/v1/office/accounts')->assertJsonPath('staff.1.two_factor', true);

        $this->postJson("/api/v1/office/accounts/{$farah->id}/reset-two-factor")->assertOk();
        $this->assertNull($farah->fresh()->two_factor_confirmed_at);
        $this->assertSame(0, ApiToken::where('user_id', $farah->id)->count());
        $this->postJson("/api/v1/office/accounts/{$this->admin->id}/reset-two-factor")->assertOk();
        $this->postJson('/api/v1/office/accounts/999/reset-two-factor')->assertNotFound();
    }

    public function test_the_audit_log_is_filtered_and_paged(): void
    {
        // Entries carry Malaysia time, not the database clock's UTC.
        Carbon::setTestNow('2026-10-05 05:40:00');
        $this->withHeader('Authorization', 'Bearer ' . $this->signIn('admin@example.test'));
        $this->postJson('/api/v1/office/accounts', ['name' => 'Farah', 'email' => 'farah@example.test', 'role' => 'coordinator']);

        $all = $this->getJson('/api/v1/office/audit')->assertOk()->assertJsonPath('more', false);
        $this->assertSame('staff_created', $all->json('entries.0.action'));
        $this->assertContains('login', $all->json('actions'));
        $this->assertSame('2026-10-05T05:40:00+08:00', $all->json('entries.0.at'));
        Carbon::setTestNow();

        $this->getJson('/api/v1/office/audit?action=login')->assertOk()
            ->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.user', 'Admin');
        $this->getJson('/api/v1/office/audit?before=' . $all->json('entries.0.id'))->assertOk()
            ->assertJsonPath('entries.0.action', 'login');
    }

    public function test_coordinators_cannot_manage_staff_or_read_the_log(): void
    {
        User::create(['name' => 'Coord', 'email' => 'coord@example.test', 'password' => 'correct horse battery',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);
        $this->withHeader('Authorization', 'Bearer ' . $this->signIn('coord@example.test'));

        $this->getJson('/api/v1/office/accounts')->assertForbidden();
        $this->getJson('/api/v1/office/audit')->assertForbidden();
        $this->postJson('/api/v1/office/accounts', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'admin'])->assertForbidden();
    }
}
