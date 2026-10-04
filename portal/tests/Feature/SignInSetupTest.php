<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SignInSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The breached-password check calls an external API; nothing here is breached.
        Http::fake();
    }

    private function user(string $role, string $status = 'active', string $email = null): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $email ?? "{$role}@example.test", 'password' => 'password',
            'role' => $role, 'status' => $status,
        ]);
    }

    private function issue(User $actor, User $target): string
    {
        $response = $this->actingAs($actor)->post(route('admin.users.temp-password', $target));
        $response->assertSessionHas('temp_password');

        return session('temp_password')['password'];
    }

    public function test_an_invited_caregiver_signs_in_with_a_temp_password_and_must_choose_their_own(): void
    {
        $coordinator = $this->user(User::ROLE_COORDINATOR);
        $carer = $this->user(User::ROLE_CAREGIVER, 'invited');

        $temp = $this->issue($coordinator, $carer);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $temp);
        $this->assertTrue(AuditLog::where('action', 'temp_password_issued')->exists());
        auth()->logout();

        $this->post(route('login'), ['email' => $carer->email, 'password' => $temp])
            ->assertRedirect(route('account.welcome'));

        // Held on the welcome page whatever they try to open.
        $this->get(route('caregiver.dashboard'))->assertRedirect(route('account.welcome'));
        $this->get(route('account.edit'))->assertRedirect(route('account.welcome'));

        // Not the temporary one again.
        $this->post(route('account.choose-password'), ['password' => $temp, 'password_confirmation' => $temp])
            ->assertSessionHasErrors('password');

        $this->post(route('account.choose-password'), [
            'password' => 'mango lantern river seven', 'password_confirmation' => 'mango lantern river seven',
        ])->assertRedirect(route('caregiver.dashboard'));

        $this->assertSame('active', $carer->fresh()->status);
        $this->get(route('caregiver.dashboard'))->assertOk();
    }

    public function test_coordinators_cannot_reset_staff_and_nobody_resets_themselves(): void
    {
        $coordinator = $this->user(User::ROLE_COORDINATOR);
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($coordinator)->post(route('admin.users.temp-password', $admin))->assertForbidden();
        $this->actingAs($coordinator)->post(route('admin.users.temp-password', $coordinator))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.temp-password', $coordinator))->assertSessionHas('temp_password');
    }

    public function test_family_and_caregivers_cannot_issue_passwords(): void
    {
        $carer = $this->user(User::ROLE_CAREGIVER);
        $family = $this->user(User::ROLE_GUARDIAN);

        $this->actingAs($carer)->post(route('admin.users.temp-password', $family))->assertForbidden();
    }

    public function test_suspended_accounts_still_cannot_sign_in(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $carer = $this->user(User::ROLE_CAREGIVER, 'suspended');

        $temp = $this->issue($admin, $carer);
        auth()->logout();

        $this->post(route('login'), ['email' => $carer->email, 'password' => $temp])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_editing_a_caregiver_does_not_skip_choosing_a_password(): void
    {
        $coordinator = $this->user(User::ROLE_COORDINATOR);

        $this->actingAs($coordinator)->post(route('admin.caregivers.store'), [
            'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active',
        ]);
        $caregiver = \App\Models\Caregiver::firstOrFail();

        $this->put(route('admin.caregivers.update', $caregiver), [
            'name' => 'Siti Nurhaliza', 'email' => 'siti@example.test', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertSame('invited', $caregiver->user->fresh()->status);
    }

    public function test_the_system_page_is_for_administrators_only_and_reports_pending_updates(): void
    {
        $this->actingAs($this->user(User::ROLE_COORDINATOR))->get(route('admin.system'))->assertForbidden();

        $admin = $this->user(User::ROLE_ADMIN, 'active', 'boss@example.test');
        $this->actingAs($admin)->get(route('admin.system'))->assertOk()->assertSee('The database is up to date.');

        // Forget one migration, as a freshly uploaded version would look.
        \DB::table('migrations')->where('migration', 'like', '%create_care_tables')->delete();
        Schema::disableForeignKeyConstraints();
        foreach (['concerns', 'visit_logs', 'shifts', 'assignments', 'care_plan_tasks', 'care_plans'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();

        $this->get(route('admin.system'))->assertSee('create_care_tables');
        $this->post(route('admin.system.migrate'))->assertSessionHas('status');
        $this->assertTrue(Schema::hasTable('care_plans'));
        $this->get(route('admin.system'))->assertSee('The database is up to date.');
    }
}
