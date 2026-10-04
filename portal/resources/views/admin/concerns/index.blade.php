@extends('layouts.app')
@section('title', 'Concerns')

@php use App\Models\Concern; @endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>Concerns</h1>
      <p>From caregivers at check-out, from families, and from clients. Each needs an owner until it is resolved.</p>
    </div>
  </div>

  <nav class="tabs">
    @foreach (['open' => 'Open', 'mine' => 'Mine', 'closed' => 'Resolved', 'all' => 'All'] as $key => $label)
      <a href="{{ route('admin.concerns.index', ['show' => $key]) }}" @class(['on' => $show === $key])>{{ $label }}</a>
    @endforeach
  </nav>

  <div class="panel">
    @if ($concerns->isEmpty())
      <div class="empty">Nothing here.</div>
    @else
      <div class="scroll">
        <table>
          <thead><tr><th>Raised</th><th>Client</th><th>About</th><th>From</th><th>Owner</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($concerns as $c)
              <tr>
                <td class="num"><a href="{{ route('admin.concerns.show', $c) }}">{{ $c->created_at->format('j M, H:i') }}</a></td>
                <td>{{ $c->patient?->name ?? '-' }}</td>
                <td>{{ str_starts_with((string) $c->detail, 'Care plan change requested') ? 'Care plan change' : (Concern::CATEGORIES[$c->category] ?? $c->category) }}</td>
                <td>{{ $c->raisedBy?->name }} <span class="muted">{{ $c->raisedBy?->role === 'guardian' ? 'family' : $c->raisedBy?->role }}</span></td>
                <td>{!! $c->assignedTo?->name ? e($c->assignedTo->name) : '<span class="pill risk">Nobody</span>' !!}</td>
                <td><span class="pill {{ $c->isOpen() ? ($c->status === 'open' ? 'new' : 'scheduled') : 'completed' }}">{{ ucfirst($c->status) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
  {{ $concerns->links() }}
@endsection
