@extends('layouts.app')
@section('title', 'Invoices')

@section('content')
  <div class="pagehead">
    <div>
      <h1>Invoices</h1>
      <p>Invoice and bank transfer. Payments are recorded here when they arrive.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-primary" href="{{ route('admin.invoices.prepare') }}">Bill a month</a>
  </div>

  <div class="stats">
    <div class="stat"><div class="n">RM {{ number_format($outstanding, 2) }}</div><div class="k">Owed to us</div></div>
    <div @class(['stat', 'alert' => $overdue > 0])><div class="n">RM {{ number_format($overdue, 2) }}</div><div class="k">Overdue</div></div>
    <div @class(['stat', 'warn' => $drafts > 0])><div class="n">{{ $drafts }}</div><div class="k">Drafts to check</div></div>
  </div>

  <nav class="tabs">
    @foreach (['open' => 'Open', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void', 'all' => 'All'] as $key => $label)
      <a href="{{ route('admin.invoices.index', ['show' => $key]) }}" @class(['on' => $filter === $key])>{{ $label }}</a>
    @endforeach
  </nav>

  <div class="panel">
    @if ($invoices->isEmpty())
      <div class="empty">No invoices here.</div>
    @else
      <div class="scroll">
        <table>
          <thead><tr><th>Number</th><th>Client</th><th>Period</th><th>Total</th><th>Owed</th><th>Due</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($invoices as $inv)
              <tr>
                <td><a href="{{ route('admin.invoices.show', $inv) }}" class="code">{{ $inv->number }}</a></td>
                <td>{{ $inv->patient?->name }}</td>
                <td class="num">{{ $inv->period_start->format('M Y') }}</td>
                <td class="num">RM {{ number_format((float) $inv->total, 2) }}</td>
                <td class="num">{{ $inv->isDraft() || $inv->status === 'void' ? '-' : 'RM ' . number_format($inv->balance(), 2) }}</td>
                <td class="num">{{ $inv->due_date?->format('j M Y') ?? '-' }}</td>
                <td><span class="pill {{ $inv->displayStatus() }}">{{ $inv->statusLabel() }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
  {{ $invoices->links() }}
@endsection
