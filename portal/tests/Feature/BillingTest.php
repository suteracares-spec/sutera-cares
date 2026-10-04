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

class BillingTest extends TestCase
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

    private function draftOctober(): Invoice
    {
        $this->actingAs($this->staff)->post(route('admin.invoices.generate'), ['month' => '2026-10'])->assertSessionHasNoErrors();

        return Invoice::firstOrFail();
    }

    public function test_a_month_becomes_a_draft_priced_by_unit_and_rate(): void
    {
        $invoice = $this->draftOctober();

        $this->assertSame('draft', $invoice->status);
        $this->assertSame($this->daughter->id, $invoice->bill_to_id, 'Addressed to the bill payer.');
        $this->assertSame(3, $invoice->lines()->count(), 'Two mornings and a massage; not the cancelled shift, not November.');
        // 2 × 5h × RM30 agreed rate + 1 session × RM120 list price.
        $this->assertEquals(420.00, (float) $invoice->total);

        // Running it again bills nothing twice.
        $this->post(route('admin.invoices.generate'), ['month' => '2026-10']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_adjustments_then_issue_then_part_and_full_payment(): void
    {
        $invoice = $this->draftOctober();

        $this->post(route('admin.invoices.lines.store', $invoice), ['description' => 'Discount: first month', 'quantity' => 1, 'rate' => -20]);
        $invoice->refresh();
        $this->assertEquals(400.00, (float) $invoice->total);
        $this->assertEquals(-20.00, (float) $invoice->surcharges);

        $this->post(route('admin.invoices.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('INV-2026-0001', $invoice->number);
        $this->assertSame('sent', $invoice->status);
        $this->assertTrue($invoice->due_date->isSameDay('2026-11-17'));

        // Locked once issued.
        $this->post(route('admin.invoices.lines.store', $invoice), ['description' => 'x', 'quantity' => 1, 'rate' => 5])
            ->assertSessionHasErrors('issue');

        $this->post(route('admin.invoices.pay', $invoice), ['amount' => 500, 'method' => 'bank_transfer', 'paid_on' => '2026-11-03'])
            ->assertSessionHasErrors('amount');
        $this->post(route('admin.invoices.pay', $invoice), ['amount' => 150, 'method' => 'duitnow', 'paid_on' => '2026-11-03']);
        $this->assertSame('part_paid', $invoice->fresh()->status);
        $this->post(route('admin.invoices.pay', $invoice), ['amount' => 250, 'method' => 'bank_transfer', 'paid_on' => '2026-11-03', 'reference' => 'MBB123']);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->balance());
    }

    public function test_overdue_is_worked_out_from_the_due_date(): void
    {
        $invoice = $this->draftOctober();
        $this->post(route('admin.invoices.issue', $invoice));

        $this->assertFalse($invoice->fresh()->isOverdue());
        Carbon::setTestNow('2026-11-18 09:00');
        $this->assertTrue($invoice->fresh()->isOverdue());
        $this->assertSame(1, Invoice::overdue()->count());
        $this->get(route('admin.invoices.index', ['show' => 'overdue']))->assertOk()->assertSee('INV-2026-0001');
    }

    public function test_a_voided_invoice_frees_its_shifts_to_be_billed_again(): void
    {
        $invoice = $this->draftOctober();
        $this->post(route('admin.invoices.issue', $invoice));

        $this->post(route('admin.invoices.void', $invoice), ['reason' => 'Wrong rate'])->assertSessionHasNoErrors();
        $this->assertSame('void', $invoice->fresh()->status);

        $this->post(route('admin.invoices.generate'), ['month' => '2026-10']);
        $this->assertSame(2, Invoice::count());
        $this->assertSame(3, Invoice::where('status', 'draft')->first()->lines()->count());
    }

    public function test_pages_render(): void
    {
        $invoice = $this->draftOctober();
        $this->get(route('admin.invoices.prepare', ['month' => '2026-11']))->assertOk()->assertSee('Puan Aminah');
        $this->get(route('admin.invoices.show', $invoice))->assertOk()->assertSee('Issue invoice');
        $this->get(route('admin.invoices.index'))->assertOk();
        $this->post(route('admin.invoices.issue', $invoice));
        $this->get(route('admin.invoices.print', $invoice))->assertOk()->assertSee('INV-2026-0001')->assertSee('RM 420.00');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Overdue invoices');
    }

    public function test_caregivers_and_family_cannot_reach_office_billing(): void
    {
        $invoice = $this->draftOctober();
        $this->actingAs($this->daughter)->get(route('admin.invoices.show', $invoice))->assertForbidden();
    }
}
