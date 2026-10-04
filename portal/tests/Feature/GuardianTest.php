<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Patient $mother;

    private Patient $father;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::create([
            'name' => 'Coordinator', 'email' => 'coord@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active',
        ]);

        $this->mother = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active']);
        $this->father = Patient::create(['code' => 'SCP-0002', 'name' => 'Encik Ahmad', 'status' => 'active']);
    }

    private function add(Patient $patient, array $overrides = [])
    {
        return $this->actingAs($this->staff)->post(route('admin.guardians.store', $patient), $overrides + [
            'name'         => 'Nor Hayati',
            'email'        => 'hayati@example.test',
            'relationship' => 'Daughter',
            'is_primary'   => '1',
        ]);
    }

    public function test_adding_a_family_member_creates_an_invited_guardian_login(): void
    {
        $this->add($this->mother)->assertSessionHasNoErrors()->assertRedirect(route('admin.patients.show', $this->mother));

        $user = User::where('email', 'hayati@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_GUARDIAN, $user->role);
        $this->assertSame('invited', $user->status);

        $link = $this->mother->guardians()->firstOrFail();
        $this->assertTrue($link->is_primary);
        $this->assertFalse($link->can_view_notes, 'Notes are off unless someone ticks them.');
        $this->assertTrue(AuditLog::where(['subject_type' => 'patient', 'action' => 'family_linked'])->exists());
    }

    public function test_the_same_person_is_linked_to_a_second_client_not_duplicated(): void
    {
        $this->add($this->mother);
        $this->add($this->father, ['name' => 'Different spelling'])->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'hayati@example.test')->count());
        $this->assertSame(2, User::where('email', 'hayati@example.test')->first()->guardianLinks()->count());

        // But not twice to the same client.
        $this->add($this->mother)->assertSessionHasErrors('email');
    }

    public function test_a_staff_or_caregiver_email_cannot_become_a_family_login(): void
    {
        $this->add($this->mother, ['email' => 'coord@example.test'])->assertSessionHasErrors('email');
        $this->assertSame(0, $this->mother->guardians()->count());
    }

    public function test_only_one_main_contact_per_client(): void
    {
        $this->add($this->mother);
        $this->add($this->mother, ['name' => 'Ali', 'email' => 'ali@example.test', 'is_primary' => '1']);

        $this->assertSame(['ali@example.test'],
            $this->mother->guardians()->where('is_primary', true)->with('user')->get()->pluck('user.email')->all());
    }

    public function test_access_switches_are_edited(): void
    {
        $this->add($this->mother);
        $link = $this->mother->guardians()->firstOrFail();

        $this->put(route('admin.guardians.update', $link), [
            'name' => 'Nor Hayati binti Ahmad', 'email' => 'hayati@example.test',
            'relationship' => 'Daughter', 'is_primary' => '1', 'can_view_notes' => '1', 'can_view_invoices' => '0',
        ])->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertTrue($link->can_view_notes);
        $this->assertFalse($link->can_view_invoices);
        $this->assertSame('Nor Hayati binti Ahmad', $link->user->name);
    }

    public function test_removing_the_last_link_suspends_the_login_and_relinking_restores_it(): void
    {
        $this->add($this->mother);
        $this->add($this->father);
        $user = User::where('email', 'hayati@example.test')->firstOrFail();

        $this->delete(route('admin.guardians.destroy', $this->mother->guardians()->first()));
        $this->assertSame('invited', $user->fresh()->status, 'Still linked to the father.');

        $this->delete(route('admin.guardians.destroy', $this->father->guardians()->first()));
        $this->assertSame('suspended', $user->fresh()->status);

        $this->add($this->mother);
        $this->assertSame('invited', $user->fresh()->status);
    }

    public function test_pages_render(): void
    {
        $this->add($this->mother);

        $this->get(route('admin.guardians.create', $this->father))->assertOk();
        $this->get(route('admin.guardians.edit', $this->mother->guardians()->first()))->assertOk()->assertSee('Remove access');
        $this->get(route('admin.patients.show', $this->mother))->assertOk()->assertSee('Nor Hayati')->assertSee('Main contact');
    }
}
