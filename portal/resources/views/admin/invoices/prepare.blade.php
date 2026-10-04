@extends('layouts.app')
@section('title', 'Bill ' . $from->format('F Y'))

@section('content')
  <div class="pagehead">
    <div>
      <h1>Bill {{ $from->format('F Y') }}</h1>
      <p>Completed shifts in the month that are not on an invoice yet.</p>
    </div>
    <div class="spacer"></div>
    <form class="filters" method="GET" action="{{ route('admin.invoices.prepare') }}" style="margin:0">
      <input type="month" name="month" value="{{ $from->format('Y-m') }}" aria-label="Month">
      <button class="btn btn-quiet" type="submit">Show</button>
    </form>
  </div>

  @if ($errors->any())
    <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="panel">
    @if ($clients->isEmpty())
      <div class="empty">Nothing to bill for {{ $from->format('F Y') }}. Shifts must be completed (checked out, or recorded by the office) to be billed.</div>
    @else
      <div class="scroll">
        <table>
          <thead><tr><th>Client</th><th>Shifts</th><th>First</th><th>Last</th><th></th></tr></thead>
          <tbody>
            @foreach ($clients as [$patient, $shifts])
              <tr>
                <td><a href="{{ route('admin.patients.show', $patient) }}">{{ $patient->name }}</a></td>
                <td class="num">{{ $shifts->count() }}</td>
                <td class="num">{{ $shifts->first()->shift_date->format('j M') }}</td>
                <td class="num">{{ $shifts->last()->shift_date->format('j M') }}</td>
                <td>
                  <form method="POST" action="{{ route('admin.invoices.generate') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $from->format('Y-m') }}">
                    <input type="hidden" name="client" value="{{ $patient->id }}">
                    <button class="btn btn-quiet btn-small" type="submit">Draft this one</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <form class="inset" method="POST" action="{{ route('admin.invoices.generate') }}">
        @csrf
        <input type="hidden" name="month" value="{{ $from->format('Y-m') }}">
        <button class="btn btn-primary" type="submit">Draft all {{ $clients->count() }}</button>
        <span class="muted">&nbsp; Drafts are not sent anywhere. Check each one, then issue it.</span>
      </form>
    @endif
  </div>
@endsection
