@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
  <div class="pagehead">
    <div>
      <h1>Today</h1>
      <p>{{ now()->format('l, j F Y') }}</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-primary" href="{{ route('admin.patients.create') }}">New client</a>
  </div>

  <div class="stats">
    <div class="stat"><div class="n">{{ $activeClients }}</div><div class="k">Active clients</div></div>
    <div class="stat"><div class="n">{{ $inAssessment }}</div><div class="k">In assessment</div></div>
    <div class="stat"><div class="n">{{ $activeCaregivers }}</div><div class="k">Active caregivers</div></div>
    <div class="stat"><div class="n">{{ $shiftsToday }}</div><div class="k">Shifts today</div></div>
    <div class="stat {{ $unstaffedToday ? 'alert' : '' }}"><div class="n">{{ $unstaffedToday }}</div><div class="k">Not checked in</div></div>
    <div class="stat {{ $newEnquiries ? 'warn' : '' }}"><div class="n">{{ $newEnquiries }}</div><div class="k">New enquiries</div></div>
  </div>

  @if ($consentMissing > 0)
    <div class="panel">
      <h2>Needs attention</h2>
      <div style="padding:16px 20px">
        <span class="pill risk">PDPA</span>
        {{ $consentMissing }} active or in-assessment {{ Str::plural('client', $consentMissing) }}
        {{ $consentMissing === 1 ? 'has' : 'have' }} no recorded consent.
        Health information is sensitive personal data and consent must be explicit and dated.
      </div>
    </div>
  @endif

  <div class="cols">
    <div class="panel">
      <h2>Recent enquiries</h2>
      @if ($recentEnquiries->isEmpty())
        <div class="empty">No enquiries yet. The website form writes here.</div>
      @else
        <div class="scroll"><table>
          <thead><tr><th>Received</th><th>From</th><th>For</th><th>Area</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($recentEnquiries as $e)
              <tr>
                <td class="num">{{ $e->created_at?->format('j M') }}</td>
                <td>{{ $e->client_name }}</td>
                <td>{{ $e->patient_name ?: '—' }}</td>
                <td>{{ $e->patient_area ?: '—' }}</td>
                <td><span class="pill {{ $e->status }}">{{ str_replace('_', ' ', $e->status) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
      @endif
    </div>

    <div class="panel">
      <h2>Vetting expiring</h2>
      @if ($checksExpiring->isEmpty())
        <div class="empty">All police checks current.</div>
      @else
        <div class="scroll"><table>
          <thead><tr><th>Caregiver</th><th>Expires</th></tr></thead>
          <tbody>
            @foreach ($checksExpiring as $c)
              <tr>
                <td><span class="code">{{ $c->code }}</span> {{ $c->user?->name }}</td>
                <td class="num">
                  <span class="pill {{ $c->police_check_expires_at->isPast() ? 'risk' : 'assessment' }}">
                    {{ $c->police_check_expires_at->format('j M Y') }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
  </div>
@endsection
