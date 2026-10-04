<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $invoice->number }} · {{ config('billing.company_name') }}</title>
<style>
  :root { --ink: #16242C; --soft: #55666E; --line: #DDD6CB; --teal: #1C6B62; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #F2EFEA; color: var(--ink); font: 15px/1.5 "Segoe UI", system-ui, sans-serif; }
  .sheet { max-width: 800px; margin: 24px auto; background: #fff; padding: 48px 52px; box-shadow: 0 2px 18px rgba(0,0,0,.08); }
  .top { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; margin-bottom: 36px; }
  h1 { font: 700 28px Georgia, serif; margin: 0 0 4px; color: var(--teal); }
  .muted { color: var(--soft); font-size: 13.5px; }
  .meta { text-align: right; }
  .meta b { display: block; font-size: 20px; }
  .cols { display: flex; gap: 40px; flex-wrap: wrap; margin-bottom: 28px; }
  .label { font-size: 11px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--soft); margin-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 9px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--soft); }
  .r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  tfoot td { border: 0; }
  tfoot .total td { font-weight: 800; font-size: 17px; border-top: 2px solid var(--ink); }
  .pay { margin-top: 32px; padding: 18px 20px; border: 1.5px solid var(--teal); border-radius: 8px; }
  .void { color: #A0503B; font-weight: 800; font-size: 22px; border: 3px solid #A0503B; display: inline-block; padding: 4px 14px; transform: rotate(-4deg); }
  .bar { max-width: 800px; margin: 16px auto 0; text-align: right; }
  .bar button { font: inherit; font-weight: 700; padding: 8px 18px; border-radius: 7px; border: 0; background: var(--teal); color: #fff; cursor: pointer; }
  footer { margin-top: 36px; font-size: 12.5px; color: var(--soft); }
  @media print {
    body { background: #fff; }
    .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
    .bar { display: none; }
  }
</style>
</head>
<body>
  <div class="bar"><button type="button" onclick="window.print()">Print or save as PDF</button></div>
  <div class="sheet">
    <div class="top">
      <div>
        <h1>{{ config('billing.company_name') }}</h1>
        <div class="muted">
          {{ config('billing.company_address') }}<br>
          @if (config('billing.company_reg')) Reg. {{ config('billing.company_reg') }}<br>@endif
          {{ collect([config('billing.company_phone'), config('billing.company_email')])->filter()->implode(' · ') }}
        </div>
      </div>
      <div class="meta">
        <div class="label">Invoice</div>
        <b>{{ $invoice->number }}</b>
        <div class="muted">Issued {{ $invoice->issued_at?->format('j M Y') }}<br>Due {{ $invoice->due_date?->format('j M Y') }}</div>
        @if ($invoice->status === 'void')<p class="void">VOID</p>@endif
      </div>
    </div>

    <div class="cols">
      <div>
        <div class="label">Billed to</div>
        {{ $invoice->billTo?->name ?? $invoice->patient?->name }}<br>
        <span class="muted">{{ $invoice->billTo?->email }}</span>
      </div>
      <div>
        <div class="label">Care for</div>
        {{ $invoice->patient?->name }}<br>
        <span class="muted">Client {{ $invoice->patient?->code }}</span>
      </div>
      <div>
        <div class="label">Period</div>
        {{ $invoice->period_start->format('j M') }}&ndash;{{ $invoice->period_end->format('j M Y') }}
      </div>
    </div>

    <table>
      <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Rate (RM)</th><th class="r">Amount (RM)</th></tr></thead>
      <tbody>
        @foreach ($invoice->lines as $line)
          <tr>
            <td>{{ $line->description }}</td>
            <td class="r">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
            <td class="r">{{ number_format((float) $line->rate, 2) }}</td>
            <td class="r">{{ number_format((float) $line->amount, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr class="total"><td colspan="3">Total</td><td class="r">RM {{ number_format((float) $invoice->total, 2) }}</td></tr>
        @if ((float) $invoice->amount_paid > 0)
          <tr><td colspan="3">Paid</td><td class="r">RM {{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
          <tr class="total"><td colspan="3">Balance due</td><td class="r">RM {{ number_format($invoice->balance(), 2) }}</td></tr>
        @endif
      </tfoot>
    </table>

    @if (config('billing.account_number') && $invoice->status !== 'void')
      <div class="pay">
        <div class="label">How to pay</div>
        Bank transfer or DuitNow to <strong>{{ config('billing.account_name') }}</strong>,
        {{ config('billing.bank_name') }}, account <strong>{{ config('billing.account_number') }}</strong>.<br>
        Please use <strong>{{ $invoice->number }}</strong> as the payment reference.
      </div>
    @endif

    <footer>
      {{ config('billing.company_name') }} provides non-medical caregivers. Hourly care is billed on the hours booked.
      Questions about this invoice: contact your coordinator.
    </footer>
  </div>
</body>
</html>
