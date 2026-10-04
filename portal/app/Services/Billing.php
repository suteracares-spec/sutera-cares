<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Patient;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Invoices are derived from work recorded: completed shifts, priced by
 * the service's unit at the assignment's agreed rate. A shift is billed
 * once; a shift on a voided invoice can be billed again.
 */
class Billing
{
    /** Completed shifts for a client in a period that no live invoice has billed yet. */
    public function unbilledShifts(Patient $patient, Carbon $from, Carbon $to): Collection
    {
        return Shift::query()
            ->with(['assignment.service'])
            ->whereHas('assignment', fn ($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'completed')
            ->whereDate('shift_date', '>=', $from->toDateString())
            ->whereDate('shift_date', '<=', $to->toDateString())
            ->whereNotIn('id', InvoiceLine::query()->whereNotNull('shift_id')
                ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))
                ->select('shift_id'))
            ->orderBy('shift_date')->orderBy('start_time')
            ->get();
    }

    /** Clients with something to bill in the period. */
    public function clientsToBill(Carbon $from, Carbon $to): Collection
    {
        return Patient::query()->orderBy('name')->get()
            ->filter(fn ($p) => $this->unbilledShifts($p, $from, $to)->isNotEmpty())
            ->values();
    }

    /** A draft invoice for one client and period, or null if there is nothing to bill. */
    public function draft(Patient $patient, Carbon $from, Carbon $to): ?Invoice
    {
        $shifts = $this->unbilledShifts($patient, $from, $to);

        if ($shifts->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($patient, $from, $to, $shifts) {
            $invoice = Invoice::create([
                'number'       => 'DRAFT-' . uniqid(),
                'patient_id'   => $patient->id,
                'bill_to_id'   => $this->billPayer($patient),
                'period_start' => $from->toDateString(),
                'period_end'   => $to->toDateString(),
                'status'       => 'draft',
            ]);
            $invoice->update(['number' => 'DRAFT-' . $invoice->id]);

            foreach ($shifts as $shift) {
                $invoice->lines()->create($this->lineFor($shift));
            }

            $invoice->recalculate();

            return $invoice;
        });
    }

    /**
     * One line per shift. Hourly services bill the booked hours, not the
     * minutes on site, because the booking is what was agreed; per-visit,
     * per-session and per-day services bill one. Live-in is priced per
     * month and billed per day at a thirtieth of the monthly rate.
     */
    public function lineFor(Shift $shift): array
    {
        $assignment = $shift->assignment;
        $service = $assignment->service;
        $rate = (float) ($assignment->rate() ?? 0);
        $when = $shift->shift_date->format('D j M') . ', ' . $shift->timeRange();

        [$quantity, $rate, $what] = match ($service?->unit) {
            'hour'  => [round($shift->scheduledMinutes() / 60, 2), $rate, ($service->name ?? 'Care') . ' (hours)'],
            'month' => [1, round($rate / 30, 2), ($service->name ?? 'Care') . ', 1 day at monthly rate ÷ 30'],
            default => [1, $rate, $service->name ?? 'Visit'],
        };

        return [
            'shift_id'    => $shift->id,
            'service_id'  => $service?->id,
            'description' => mb_substr("{$what}: {$when}", 0, 300),
            'quantity'    => $quantity,
            'rate'        => $rate,
            'amount'      => round($quantity * $rate, 2),
        ];
    }

    /** Who the invoice is addressed to: the bill payer, else the main contact, else the client. */
    public function billPayer(Patient $patient): ?int
    {
        $links = Guardian::where('patient_id', $patient->id)->get();

        return $links->firstWhere('is_bill_payer', true)?->user_id
            ?? $links->firstWhere('is_primary', true)?->user_id
            ?? $patient->user_id;
    }
}
