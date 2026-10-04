<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Caregiver;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Patient $patient;

    private Assignment $care;

    private Assignment $massage;

    private User $daughter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00');

        $this->staff = User::create(['name' => 'Coord', 'email' => 'c@example.test', 'password' => 'password',
            'role' => User::ROLE_COORDINATOR, 'status' => 'active']);

        $this->patient = Patient::create(['code' => 'SCP-0001', 'name' => 'Puan Aminah', 'status' => 'active']);
        $this->daughter = User::create(['name' => 'Nor Hayati', 'email' => 'h@example.test', 'password' => 'password',
            'role' => User::ROLE_GUARDIAN, 'status' => 'active']);
        $this->patient->guardians()->create(['user_id' => $this->daughter->id, 'is_bill_payer' => true]);

        $cgUser = User::create(['name' => 'Siti', 'email' => 's@example.test', 'password' => 'password',
            'role' => User::ROLE_CAREGIVER, 'status' => 'active']);
        $siti = Caregiver::create(['user_id' => $cgUser->id, 'code' => 'CG-001', 'status' => 'active']);

        $hourly = Service::create(['code' => 'HOURLY-PC', 'name' => 'Personal care', 'category' => 'personal_care', 'unit' => 'hour', 'base_rate' => 35]);
        $session = Service::create(['code' => 'MASSAGE-60', 'name' => 'Massage', 'category' => 'wellness', 'unit' => 'session', 'base_rate' => 120]);

        $this->care = $this->patient->assignments()->create(['caregiver_id' => $siti->id, 'service_id' => $hourly->id,
            'start_date' => '2026-10-01', 'charge_rate' => 30, 'status' => 'active']);
        $this->massage = $this->patient->assignments()->create(['caregiver_id' => $siti->id, 'service_id' => $session->id,
            'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'status' => 'active']);

        $token = $this->postJson('/api/v1/login', ['email' => 'c@example.test', 'password' => 'password'])->json('token');
        $this->withHeader('Authorization', "Bearer {$token}");

        // October: two completed 5-hour mornings, one cancelled, one massage. November: one completed.
        $this->care->shifts()->create(['shift_date' => '2026-10-05', 'start_time' => '08:00', 'end_time' => '13:00', 'status' => 'completed']);
        $this->care->shifts()->create(['shift_date' => '2026-10-06', 'start_time' => '08:00', 'end_time' => '13:00', 'status' => 'completed']);
        $this->care->shifts()->create(['shift_date' => '2026-10-07', 'start_time' => '08:00', 'end_time' => '13:00', 'status' => 'cancelled']);
        $this->massage->shifts()->create(['shift_date' => '2026-10-20', 'start_time' => '15:00', 'end_time' => '16:00', 'status' => 'completed']);
        $this->care->shifts()->create(['shift_date' => '2026-11-02', 'start_time' => '08:00', 'end_time' => '13:00', 'status' => 'completed']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_office_bills_a_month_adjusts_issues_and_takes_payment(): void
    {
        $this->getJson('/api/v1/office/invoices/prepare?month=2026-10')->assertOk()
            ->assertJsonPath('label', 'October 2026')
            ->assertJsonPath('clients.0.shifts', 3)->assertJsonPath('clients.0.amount', 420);

        $id = $this->postJson('/api/v1/office/invoices/generate', ['month' => '2026-10'])->assertOk()
            ->assertJsonPath('message', 'Draft invoice created. Check it, then issue it.')->json('invoice_ids.0');

        $this->getJson("/api/v1/office/invoices/{$id}")->assertOk()
            ->assertJsonPath('status', 'draft')->assertJsonPath('total', 420)
            ->assertJsonPath('bill_to', 'Nor Hayati')->assertJsonCount(3, 'lines');

        // A discount, then a mistaken line removed again.
        $this->postJson("/api/v1/office/invoices/{$id}/lines", ['description' => 'Loyalty discount', 'quantity' => 1, 'rate' => -20])
            ->assertOk()->assertJsonPath('total', 400)->assertJsonPath('adjustments', -20);
        $line = $this->postJson("/api/v1/office/invoices/{$id}/lines", ['description' => 'Oops', 'quantity' => 1, 'rate' => 5])->json('lines.4.id');
        $this->deleteJson("/api/v1/office/invoices/{$id}/lines/{$line}")->assertOk()->assertJsonPath('total', 400);

        $this->getJson("/api/v1/office/invoices/{$id}/html")->assertOk()
            ->assertJsonPath('filename', "DRAFT-{$id}.pdf")->assertSee('Puan Aminah');

        $this->postJson("/api/v1/office/invoices/{$id}/issue")->assertOk()
            ->assertJsonPath('number', 'INV-2026-0001')->assertJsonPath('status', 'sent')->assertJsonPath('can_pay', true);

        // Issued invoices are no longer editable.
        $this->postJson("/api/v1/office/invoices/{$id}/lines", ['description' => 'Late', 'quantity' => 1, 'rate' => 5])
            ->assertStatus(422)->assertJsonValidationErrors('issue');

        $this->postJson("/api/v1/office/invoices/{$id}/payments", ['amount' => 500, 'method' => 'bank_transfer', 'paid_on' => '2026-11-03'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/office/invoices/{$id}/payments", ['amount' => 150, 'method' => 'duitnow', 'reference' => 'DN123', 'paid_on' => '2026-11-03'])
            ->assertOk()->assertJsonPath('status', 'part_paid')->assertJsonPath('balance', 250)->assertJsonPath('can_void', false);
        $this->postJson("/api/v1/office/invoices/{$id}/payments", ['amount' => 250, 'method' => 'bank_transfer', 'paid_on' => '2026-11-03'])
            ->assertOk()->assertJsonPath('message', 'Payment recorded. Paid in full.')->assertJsonCount(2, 'payments');

        $this->getJson('/api/v1/office/invoices?show=paid')->assertOk()->assertJsonPath('invoices.0.number', 'INV-2026-0001');
    }

    public function test_drafts_are_deleted_and_issued_invoices_voided(): void
    {
        $id = $this->postJson('/api/v1/office/invoices/generate', ['month' => '2026-10'])->json('invoice_ids.0');
        $this->deleteJson("/api/v1/office/invoices/{$id}")->assertOk();
        $this->assertDatabaseCount('invoices', 0);

        $id = $this->postJson('/api/v1/office/invoices/generate', ['month' => '2026-10'])->json('invoice_ids.0');
        $this->postJson("/api/v1/office/invoices/{$id}/issue")->assertOk();
        $this->postJson("/api/v1/office/invoices/{$id}/void", ['reason' => 'Wrong client'])
            ->assertOk()->assertJsonPath('status', 'void');

        // Its shifts can be billed again.
        $this->getJson('/api/v1/office/invoices/prepare?month=2026-10')->assertJsonPath('clients.0.shifts', 3);
    }

    public function test_caregivers_cannot_see_billing(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->postJson('/api/v1/login', ['email' => 's@example.test', 'password' => 'password'])->json('token'));
        $this->getJson('/api/v1/office/invoices')->assertForbidden();
    }
}
