<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CarePlanTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::create([
            'name' => 'Coordinator', 'email' => 'coord@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active',
            'consent_given_at' => now()->subDay(), 'consent_by' => 'Daughter',
        ]);
    }

    private function task(string $description, string $category = 'personal_care'): array
    {
        return ['category' => $category, 'description' => $description, 'frequency' => 'every_visit', 'time_of_day' => 'any'];
    }

    private function draftWith(array $tasks, ?string $from = null): CarePlan
    {
        $this->actingAs($this->staff)->post(route('admin.care-plans.store', $this->patient));
        $plan = $this->patient->carePlans()->where('status', 'draft')->firstOrFail();

        $this->put(route('admin.care-plans.update', $plan), [
            'effective_from' => $from ?? today()->toDateString(),
            'tasks'          => $tasks,
        ])->assertSessionHasNoErrors();

        return $plan->fresh();
    }

    private function activate(CarePlan $plan)
    {
        return $this->post(route('admin.care-plans.activate', $plan), [
            'agreed_by' => 'Nor Hayati (daughter)',
            'agreed_at' => today()->toDateString(),
        ]);
    }

    public function test_a_draft_is_written_and_activated(): void
    {
        $plan = $this->draftWith([
            $this->task('Bed bath and change of clothes'),
            $this->task('', ''),                           // a spare row, ignored
            $this->task('Prepare lunch', 'household'),
        ]);

        $this->assertSame(['Bed bath and change of clothes', 'Prepare lunch'],
            $plan->tasks->pluck('description')->all());

        $this->activate($plan)->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('active', $plan->status);
        $this->assertSame('Nor Hayati (daughter)', $plan->agreed_by);
        $this->assertTrue(AuditLog::where(['subject_type' => 'care_plan', 'action' => 'activated'])->exists());
    }

    public function test_clinical_tasks_are_refused_and_everyday_ones_are_not(): void
    {
        $this->actingAs($this->staff)->post(route('admin.care-plans.store', $this->patient));
        $plan = $this->patient->carePlans()->firstOrFail();

        foreach (['Give insulin injection', 'Change the dressing on her wound', 'Flush the catheter', 'Check IV line'] as $clinical) {
            $this->put(route('admin.care-plans.update', $plan), [
                'effective_from' => today()->toDateString(),
                'tasks'          => [$this->task($clinical)],
            ])->assertSessionHasErrors('tasks.0.description');
        }

        // "Dressing" as in getting dressed, and prompting medication, are care.
        $this->put(route('admin.care-plans.update', $plan), [
            'effective_from' => today()->toDateString(),
            'tasks'          => [$this->task('Help with dressing'), $this->task('Remind to take medication')],
        ])->assertSessionHasNoErrors();
    }

    public function test_an_agreed_plan_cannot_be_edited_only_revised(): void
    {
        $v1 = $this->draftWith([$this->task('Bed bath')], today()->subDays(10)->toDateString());
        $this->activate($v1);

        $this->get(route('admin.care-plans.edit', $v1))->assertRedirect(route('admin.care-plans.show', $v1));
        $this->put(route('admin.care-plans.update', $v1), ['effective_from' => today()->toDateString(), 'tasks' => []]);
        $this->assertSame(['Bed bath'], $v1->fresh()->tasks->pluck('description')->all());

        // A revision starts as a copy of the current version.
        $this->post(route('admin.care-plans.store', $this->patient));
        $v2 = $this->patient->carePlans()->where('version', 2)->firstOrFail();
        $this->assertSame(['Bed bath'], $v2->tasks->pluck('description')->all());

        // Starting again while a draft exists carries on with that draft.
        $this->post(route('admin.care-plans.store', $this->patient))->assertRedirect(route('admin.care-plans.edit', $v2));
        $this->assertSame(2, $this->patient->carePlans()->count());

        $this->put(route('admin.care-plans.update', $v2), [
            'effective_from' => today()->toDateString(),
            'tasks'          => [$this->task('Bed bath'), $this->task('Evening walk', 'mobility')],
        ]);
        $this->activate($v2)->assertSessionHasNoErrors();

        $v1->refresh();
        $this->assertSame('superseded', $v1->status);
        $this->assertTrue($v1->effective_to->isSameDay(today()->subDay()));
        $this->assertTrue($this->patient->activeCarePlan()->is($v2));
    }

    public function test_activation_needs_consent_and_tasks(): void
    {
        $empty = $this->draftWith([]);
        $this->activate($empty)->assertSessionHasErrors('agreed_by');
        $this->assertSame('draft', $empty->fresh()->status);

        $this->patient->update(['consent_given_at' => null]);
        $plan = $this->draftWith([$this->task('Bed bath')]);
        $this->activate($plan)->assertSessionHasErrors('agreed_by');
        $this->assertSame('draft', $plan->fresh()->status);
    }

    public function test_only_drafts_can_be_discarded(): void
    {
        $plan = $this->draftWith([$this->task('Bed bath')]);
        $this->activate($plan);

        $this->delete(route('admin.care-plans.destroy', $plan));
        $this->assertModelExists($plan);

        $this->post(route('admin.care-plans.store', $this->patient));
        $draft = $this->patient->carePlans()->where('status', 'draft')->firstOrFail();
        $this->delete(route('admin.care-plans.destroy', $draft))->assertRedirect(route('admin.patients.show', $this->patient));
        $this->assertModelMissing($draft);
    }

    public function test_notes_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->staff)->post(route('admin.care-plans.store', $this->patient));
        $plan = $this->patient->carePlans()->firstOrFail();
        $this->put(route('admin.care-plans.update', $plan), [
            'effective_from' => today()->toDateString(),
            'notes'          => 'Hard of hearing on the left',
        ]);

        $raw = DB::table('care_plans')->where('id', $plan->id)->value('notes');
        $this->assertStringNotContainsString('hearing', $raw);
        $this->assertSame('Hard of hearing on the left', $plan->fresh()->notes);
    }

    public function test_pages_render_and_viewing_is_audited(): void
    {
        $plan = $this->draftWith([$this->task('Bed bath')]);

        $this->get(route('admin.care-plans.edit', $plan))->assertOk()->assertSee('Bed bath');
        $this->get(route('admin.care-plans.show', $plan))->assertOk()->assertSee('Activate version 1');
        $this->get(route('admin.patients.show', $this->patient))->assertOk()->assertSee('Version 1');

        $this->assertTrue(AuditLog::where(['subject_type' => 'care_plan', 'action' => 'viewed'])->exists());
    }

    public function test_family_and_caregivers_cannot_reach_care_plan_admin(): void
    {
        $plan = $this->draftWith([$this->task('Bed bath')]);

        foreach ([User::ROLE_GUARDIAN, User::ROLE_CAREGIVER] as $role) {
            $user = User::create([
                'name' => $role, 'email' => "{$role}@example.test", 'password' => 'password',
                'role' => $role, 'status' => 'active',
            ]);

            $this->actingAs($user)->get(route('admin.care-plans.show', $plan))->assertForbidden();
            $this->actingAs($user)->post(route('admin.care-plans.activate', $plan), ['agreed_by' => 'x', 'agreed_at' => today()->toDateString()])
                ->assertForbidden();
        }

        $this->assertSame('draft', $plan->fresh()->status);
    }
}
