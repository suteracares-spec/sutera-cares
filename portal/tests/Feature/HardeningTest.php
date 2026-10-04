<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portal.require_two_factor' => true]);
        Http::fake();
    }

    private function user(string $role, string $email, string $status = 'active'): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $email, 'password' => 'correct horse battery',
            'role' => $role, 'status' => $status]);
    }

    private function signIn(User $user): void
    {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'correct horse battery'])->assertSessionHasNoErrors();
    }

    public function test_totp_matches_the_rfc_test_vectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';   // "12345678901234567890"
        $this->assertSame('287082', Totp::code($secret, intdiv(59, 30)));
        $this->assertSame('081804', Totp::code($secret, intdiv(1111111109, 30)));
        $this->assertTrue(Totp::verify($secret, '287 082', 59));
        $this->assertFalse(Totp::verify($secret, '287082', 59 + 120));
    }

    public function test_staff_must_set_up_two_factor_then_pass_it_every_sign_in(): void
    {
        $coord = $this->user(User::ROLE_COORDINATOR, 'coord@example.test');
        $this->signIn($coord);

        $this->get(route('admin.dashboard'))->assertRedirect(route('two-factor.setup'));
        $this->get(route('two-factor.setup'))->assertOk()->assertSee('Enter a setup key');
        $secret = session('two_factor_pending');

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $page = $this->post(route('two-factor.confirm'), ['code' => Totp::code($secret, intdiv(time(), 30))]);
        $page->assertOk()->assertSee('Save these recovery codes');
        $this->assertTrue($coord->fresh()->hasTwoFactor());
        $this->get(route('admin.dashboard'))->assertOk();

        // Next sign-in: password, then the code.
        $this->post(route('logout'));
        $this->signIn($coord);
        $this->get(route('admin.patients.index'))->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.verify'), ['code' => Totp::code($secret, intdiv(time(), 30))])
            ->assertRedirect(route('admin.patients.index'));
        $this->get(route('admin.patients.index'))->assertOk();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $this->signIn($admin);
        $this->get(route('two-factor.setup'));
        $secret = session('two_factor_pending');
        $codes = $this->post(route('two-factor.confirm'), ['code' => Totp::code($secret, intdiv(time(), 30))])->viewData('codes');
        $this->assertCount(8, $codes);

        $this->post(route('logout'));
        $this->signIn($admin);
        $this->post(route('two-factor.verify'), ['code' => strtoupper($codes[0])])->assertSessionHasNoErrors();
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
        $this->assertTrue(AuditLog::where('action', 'two_factor_recovery_used')->exists());

        $this->post(route('logout'));
        $this->signIn($admin);
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertSessionHasErrors('code');
    }

    public function test_caregivers_and_families_are_not_asked_for_a_second_factor(): void
    {
        $family = $this->user(User::ROLE_GUARDIAN, 'family@example.test');
        $this->signIn($family);
        $this->get(route('guardian.dashboard'))->assertOk();
    }

    public function test_administrators_manage_staff_and_coordinators_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $coord = $this->user(User::ROLE_COORDINATOR, 'coord@example.test');
        config(['portal.require_two_factor' => false]);

        $this->actingAs($coord)->get(route('admin.staff'))->assertForbidden();
        $this->get(route('admin.audit'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.staff'))->assertOk()->assertSee('coord@example.test');
        $this->post(route('admin.staff.store'), ['name' => 'Faridah', 'email' => 'faridah@example.test', 'role' => 'coordinator'])
            ->assertSessionHas('temp_password');
        $new = User::where('email', 'faridah@example.test')->firstOrFail();
        $this->assertSame('invited', $new->status);

        $this->post(route('admin.staff.status', $coord));
        $this->assertSame('suspended', $coord->fresh()->status);
        $this->post(route('admin.staff.status', $coord));
        $this->assertNotSame('suspended', $coord->fresh()->status);

        // Never yourself, never the last administrator.
        $this->post(route('admin.staff.status', $admin))->assertForbidden();

        $coord->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $this->post(route('admin.staff.reset-2fa', $coord));
        $this->assertFalse($coord->fresh()->hasTwoFactor());

        $this->get(route('admin.audit'))->assertOk()->assertSee('staff suspended')->assertSee('two factor reset');
        $this->get(route('admin.audit', ['subject' => 'user:' . $coord->id]))->assertOk();
    }

    public function test_just_after_an_upload_the_admin_can_still_reach_the_system_page_to_apply_updates(): void
    {
        // New code, old database: the two-factor columns are not there yet.
        \DB::table('migrations')->where('migration', 'like', '%two_factor_confirmation%')->delete();
        \Illuminate\Support\Facades\Schema::table('users', function ($table) {
            $table->dropColumn(['two_factor_confirmed_at', 'two_factor_recovery_codes']);
        });

        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $this->signIn($admin);

        $this->get(route('admin.system'))->assertOk()->assertSee('two_factor_confirmation');
        $this->post(route('admin.system.migrate'))->assertSessionHas('status');
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('users', 'two_factor_confirmed_at'));
    }

    public function test_the_two_factor_requirement_can_be_switched_off_to_recover_access(): void
    {
        config(['portal.require_two_factor' => false]);
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $this->signIn($admin);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.system'))->assertOk()->assertSee('PORTAL_REQUIRE_2FA is false');
    }
}
