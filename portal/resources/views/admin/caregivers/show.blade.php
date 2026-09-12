@extends('layouts.app')
@section('title', $caregiver->user?->name ?? 'Caregiver')

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $caregiver->user?->name }}</h1>
      <p><span class="code">{{ $caregiver->code }}</span> &middot;
         <span class="pill {{ $caregiver->status }}">{{ ucfirst($caregiver->status) }}</span>
         @if ($caregiver->isPlaceable())
           <span class="pill active">Placeable</span>
         @else
           <span class="pill risk">Not placeable</span>
         @endif
      </p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.caregivers.index') }}">All caregivers</a>
    <a class="btn btn-primary" href="{{ route('admin.caregivers.edit', $caregiver) }}">Edit</a>
  </div>

  @unless ($caregiver->isPlaceable())
    <div class="errors">
      <strong>Not placeable.</strong>
      @if (! $caregiver->right_to_work_verified) Right to work is not verified. @endif
      @if ($caregiver->police_check_expires_at === null)
        No police check on file.
      @elseif ($caregiver->police_check_expires_at->isPast())
        Police check expired {{ $caregiver->police_check_expires_at->format('j M Y') }}.
      @endif
      @if ($caregiver->status !== 'active') Status is {{ $caregiver->status }}. @endif
    </div>
  @endunless

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Caregiver record</h2>
        <dl class="detail">
          <dt>Email</dt><dd>{{ $caregiver->user?->email }}</dd>
          <dt>Phone</dt><dd>{{ $caregiver->user?->phone ?: 'Not recorded' }}</dd>
          <dt>IC number</dt><dd>{{ $caregiver->ic_number ?: 'Not recorded' }}</dd>
          <dt>Date of birth</dt>
          <dd>{{ $caregiver->dob ? $caregiver->dob->format('j F Y') . ' (age ' . $caregiver->dob->age . ')' : 'Not recorded' }}</dd>
          <dt>Gender</dt><dd>{{ $caregiver->gender ? ucfirst($caregiver->gender) : 'Not recorded' }}</dd>
          <dt>Base area</dt><dd>{{ $caregiver->base_area ?: 'Not recorded' }}</dd>
          <dt>Will travel</dt>
          <dd>{{ $caregiver->max_travel_km ? $caregiver->max_travel_km . ' km' : 'Not recorded' }}{{ $caregiver->has_own_transport ? ' (own transport)' : '' }}</dd>
          <dt>Languages</dt><dd>{{ $caregiver->languages ?: 'Not recorded' }}</dd>
          <dt>Skills</dt><dd>{{ $caregiver->skills ?: 'Not recorded' }}</dd>
          <dt>Pay rate</dt>
          <dd>{{ $caregiver->hourly_rate ? 'RM ' . number_format((float) $caregiver->hourly_rate, 2) . ' / hour' : 'Not set' }}</dd>
        </dl>
      </div>

      <div class="panel">
        <h2>Clients assigned</h2>
        @if ($caregiver->assignments->isEmpty())
          <div class="empty">No clients assigned.</div>
        @else
          <div class="scroll">
            <table>
              <thead><tr><th>Client</th><th>Role</th><th>From</th><th>Status</th></tr></thead>
              <tbody>
                @foreach ($caregiver->assignments as $a)
                  <tr>
                    <td><span class="code">{{ $a->patient?->code }}</span> {{ $a->patient?->name }}</td>
                    <td>{{ ucfirst($a->role) }}</td>
                    <td class="num">{{ $a->start_date }}</td>
                    <td><span class="pill {{ $a->status }}">{{ ucfirst($a->status) }}</span></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Vetting</h2>
        <dl class="detail">
          <dt>Right to work</dt>
          <dd>
            @if ($caregiver->right_to_work_verified)
              <span class="pill active">Verified</span>
            @else
              <span class="pill risk">Not verified</span>
            @endif
          </dd>
          <dt>Police check</dt>
          <dd>
            @if ($caregiver->police_check_expires_at === null)
              <span class="pill risk">None on file</span>
            @elseif ($caregiver->police_check_expires_at->isPast())
              <span class="pill risk">Expired {{ $caregiver->police_check_expires_at->format('j M Y') }}</span>
            @elseif ($caregiver->policeCheckExpiringSoon())
              <span class="pill assessment">Expires {{ $caregiver->police_check_expires_at->format('j M Y') }}</span>
            @else
              <span class="pill active">Valid to {{ $caregiver->police_check_expires_at->format('j M Y') }}</span>
            @endif
          </dd>
          <dt>Portal access</dt>
          <dd><span class="pill {{ $caregiver->user?->status }}">{{ ucfirst($caregiver->user?->status ?? 'unknown') }}</span></dd>
          <dt>Last signed in</dt>
          <dd>{{ $caregiver->user?->last_login_at?->diffForHumans() ?: 'Never' }}</dd>
        </dl>
      </div>

      <div class="panel">
        <h2>Archive</h2>
        <div style="padding:16px 20px">
          <p style="margin:0 0 12px;color:var(--ink-soft);font-size:14px">
            Archiving revokes portal access immediately and marks them as left.
            Their past visit records stay attached, because those are evidence.
          </p>
          <form method="POST" action="{{ route('admin.caregivers.destroy', $caregiver) }}"
                onsubmit="return confirm('Archive {{ $caregiver->code }} and revoke access?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" type="submit">Archive caregiver</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
