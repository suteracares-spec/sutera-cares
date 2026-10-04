@extends('layouts.app')
@section('title', $invoice->number)

@php use App\Models\Payment; @endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $invoice->isDraft() ? 'Draft invoice' : $invoice->number }}</h1>
      <p><a href="{{ route('admin.patients.show', $invoice->patient) }}">{{ $invoice->patient?->name }}</a>
         &middot; {{ $invoice->period_start->format('j M') }}&ndash;{{ $invoice->period_end->format('j M Y') }}
         &middot; <span class="pill {{ $invoice->displayStatus() }}">{{ $invoice->statusLabel() }}</span></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.invoices.index') }}">All invoices</a>
    @unless ($invoice->isDraft())
      <a class="btn btn-quiet" href="{{ route('admin.invoices.print', $invoice) }}" target="_blank">Print / PDF</a>
    @endunless
  </div>

  @if ($errors->any())
    <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Lines</h2>
        <div class="scroll">
          <table>
            <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th>@if ($invoice->isDraft())<th></th>@endif</tr></thead>
            <tbody>
              @foreach ($invoice->lines as $line)
                <tr>
                  <td>{{ $line->description }}@unless ($line->shift_id) <span class="pill">Adjustment</span>@endunless</td>
                  <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                  <td class="num">{{ number_format((float) $line->rate, 2) }}</td>
                  <td class="num">{{ number_format((float) $line->amount, 2) }}</td>
                  @if ($invoice->isDraft())
                    <td>
                      <form method="POST" action="{{ route('admin.invoices.lines.destroy', [$invoice, $line]) }}">
                        @csrf @method('DELETE')
                        <button class="linkbtn" type="submit" title="Remove line">Remove</button>
                      </form>
                    </td>
                  @endif
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              @if ((float) $invoice->surcharges != 0)
                <tr><td colspan="3">Care provided</td><td class="num">{{ number_format((float) $invoice->subtotal, 2) }}</td>@if ($invoice->isDraft())<td></td>@endif</tr>
                <tr><td colspan="3">Adjustments</td><td class="num">{{ number_format((float) $invoice->surcharges, 2) }}</td>@if ($invoice->isDraft())<td></td>@endif</tr>
              @endif
              <tr class="total"><td colspan="3">Total (RM)</td><td class="num">{{ number_format((float) $invoice->total, 2) }}</td>@if ($invoice->isDraft())<td></td>@endif</tr>
              @if ((float) $invoice->amount_paid > 0)
                <tr><td colspan="3">Paid</td><td class="num">{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
                <tr class="total"><td colspan="3">Still owed</td><td class="num">{{ number_format($invoice->balance(), 2) }}</td></tr>
              @endif
            </tfoot>
          </table>
        </div>
      </div>

      @if ($invoice->isDraft())
        <div class="panel">
          <h2>Add an adjustment</h2>
          <form class="inset" method="POST" action="{{ route('admin.invoices.lines.store', $invoice) }}">
            @csrf
            <div class="field">
              <label for="l_desc">Description *</label>
              <input id="l_desc" name="description" required maxlength="300" placeholder="e.g. Public holiday surcharge, 31 Aug; or Discount: first month">
            </div>
            <div class="grid2">
              <div class="field">
                <label for="l_qty">Quantity *</label>
                <input id="l_qty" name="quantity" type="number" step="0.01" min="0.01" value="1" required>
              </div>
              <div class="field">
                <label for="l_rate">Rate (RM) *</label>
                <input id="l_rate" name="rate" type="number" step="0.01" required>
                <p class="hint">Negative for a discount.</p>
              </div>
            </div>
            <button class="btn btn-quiet" type="submit">Add line</button>
          </form>
        </div>
      @endif

      @if ($invoice->payments->isNotEmpty())
        <div class="panel">
          <h2>Payments</h2>
          <div class="scroll">
            <table>
              <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th>Recorded by</th></tr></thead>
              <tbody>
                @foreach ($invoice->payments as $p)
                  <tr>
                    <td class="num">{{ $p->paid_on->format('j M Y') }}</td>
                    <td>{{ Payment::METHODS[$p->method] ?? $p->method }}</td>
                    <td>{{ $p->reference ?: '-' }}</td>
                    <td class="num">{{ number_format((float) $p->amount, 2) }}</td>
                    <td>{{ $p->recordedBy?->name }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

    <div>
      <div class="panel">
        <h2>Billed to</h2>
        <dl class="detail">
          <dt>Name</dt><dd>{{ $invoice->billTo?->name ?? $invoice->patient?->name }}</dd>
          <dt>Contact</dt><dd>{{ $invoice->billTo?->email ?? 'No family bill payer recorded' }}<br>{{ $invoice->billTo?->phone }}</dd>
          @if ($invoice->issued_at)
            <dt>Issued</dt><dd>{{ $invoice->issued_at->format('j M Y') }}</dd>
            <dt>Due</dt><dd @class(['warn' => $invoice->isOverdue()])>{{ $invoice->due_date?->format('j M Y') }}</dd>
          @endif
        </dl>
      </div>

      @if ($invoice->isDraft())
        <div class="panel">
          <h2>Issue</h2>
          <form class="inset" method="POST" action="{{ route('admin.invoices.issue', $invoice) }}"
                onsubmit="return confirm('Issue this invoice? It gets a number and can no longer be edited.')">
            @csrf
            <p class="muted">Issuing gives it a number and a due date {{ config('billing.due_days') }} days on, and locks it.
              The family sees it in their portal if they are allowed to see invoices.</p>
            <button class="btn btn-primary" type="submit">Issue invoice</button>
          </form>
          <form class="inset" method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" style="padding-top:0"
                onsubmit="return confirm('Delete this draft? Its shifts become unbilled again.')">
            @csrf @method('DELETE')
            <button class="linkbtn" type="submit">Delete draft</button>
          </form>
        </div>
      @elseif (in_array($invoice->status, ['sent', 'part_paid', 'overdue'], true))
        <div class="panel">
          <h2>Record a payment</h2>
          <form class="inset" method="POST" action="{{ route('admin.invoices.pay', $invoice) }}">
            @csrf
            <div class="field">
              <label for="p_amount">Amount (RM) *</label>
              <input id="p_amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance() }}"
                     value="{{ old('amount', number_format($invoice->balance(), 2, '.', '')) }}" required>
            </div>
            <div class="grid2">
              <div class="field">
                <label for="p_method">Method *</label>
                <select id="p_method" name="method">
                  @foreach (Payment::METHODS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                </select>
              </div>
              <div class="field">
                <label for="p_on">Received on *</label>
                <input id="p_on" name="paid_on" type="date" required value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
              </div>
            </div>
            <div class="field">
              <label for="p_ref">Bank reference</label>
              <input id="p_ref" name="reference" maxlength="120">
            </div>
            <button class="btn btn-primary" type="submit">Record payment</button>
          </form>
        </div>

        @if ($invoice->payments->isEmpty())
          <div class="panel">
            <h2>Void</h2>
            <form class="inset" method="POST" action="{{ route('admin.invoices.void', $invoice) }}"
                  onsubmit="return confirm('Void this invoice?')">
              @csrf
              <div class="field">
                <label for="v_reason">Why *</label>
                <input id="v_reason" name="reason" required maxlength="200" placeholder="e.g. Wrong rate; reissued">
              </div>
              <button class="btn btn-danger" type="submit">Void invoice</button>
            </form>
          </div>
        @endif
      @endif
    </div>
  </div>
@endsection
