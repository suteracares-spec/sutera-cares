@extends('layouts.app')
@section('title', 'Clients')

@section('content')
  <div class="pagehead">
    <div><h1>Clients</h1><p>{{ $patients->total() }} {{ Str::plural('record', $patients->total()) }}</p></div>
    <div class="spacer"></div>
    <a class="btn btn-primary" href="{{ route('admin.patients.create') }}">New client</a>
  </div>

  <form class="filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, reference or area">
    <select name="status">
      <option value="">Any status</option>
      @foreach (['enquiry', 'assessment', 'active', 'paused', 'closed'] as $s)
        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <button class="btn btn-quiet" type="submit">Filter</button>
  </form>

  <div class="panel">
    @if ($patients->isEmpty())
      <div class="empty">No clients match. Create one to get started.</div>
    @else
      <div class="scroll">
        <table>
          <thead>
            <tr><th>Ref</th><th>Name</th><th>Area</th><th>Mobility</th><th>Consent</th><th>Status</th></tr>
          </thead>
          <tbody>
            @foreach ($patients as $p)
              <tr>
                <td class="code">{{ $p->code }}</td>
                <td><a href="{{ route('admin.patients.show', $p) }}">{{ $p->name }}</a></td>
                <td>{{ $p->area ?: '-' }}</td>
                <td>{{ $p->mobility_level ? ucfirst(str_replace('_', ' ', $p->mobility_level)) : '-' }}</td>
                <td>
                  @if ($p->hasConsent())
                    <span class="pill active">Recorded</span>
                  @else
                    <span class="pill risk">Missing</span>
                  @endif
                </td>
                <td><span class="pill {{ $p->status }}">{{ ucfirst($p->status) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  {{ $patients->links() }}
@endsection
