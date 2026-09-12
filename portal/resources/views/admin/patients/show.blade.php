@extends('layouts.app')
@section('title', $patient->name)

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $patient->name }}</h1>
      <p><span class="code">{{ $patient->code }}</span> &middot;
         <span class="pill {{ $patient->status }}">{{ ucfirst($patient->status) }}</span></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.patients.index') }}">All clients</a>
    <a class="btn btn-primary" href="{{ route('admin.patients.edit', $patient) }}">Edit</a>
  </div>

  @unless ($patient->hasConsent())
    <div class="errors">
      <strong>No consent recorded.</strong> Health information is sensitive personal
      data under the PDPA. Record explicit, dated consent before care begins.
    </div>
  @endunless

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Client record</h2>
        <dl class="detail">
          <dt>IC number</dt><dd>{{ $patient->ic_number ?: 'Not recorded' }}</dd>
          <dt>Date of birth</dt>
          <dd>{{ $patient->dob ? $patient->dob->format('j F Y') . ' (age ' . $patient->dob->age . ')' : 'Not recorded' }}</dd>
          <dt>Gender</dt><dd>{{ $patient->gender ? ucfirst($patient->gender) : 'Not recorded' }}</dd>
          <dt>Address</dt><dd>{{ $patient->address ?: 'Not recorded' }}</dd>
          <dt>Area</dt><dd>{{ $patient->area ?: 'Not recorded' }} {{ $patient->postcode }}</dd>
          <dt>Mobility</dt>
          <dd>{{ $patient->mobility_level ? ucfirst(str_replace('_', ' ', $patient->mobility_level)) : 'Not recorded' }}</dd>
          <dt>Languages</dt><dd>{{ $patient->languages ?: 'Not recorded' }}</dd>
          <dt>Allergies</dt><dd>{{ $patient->allergies ?: 'None recorded' }}</dd>
          <dt>Notes</dt><dd>{{ $patient->notes ?: 'None' }}</dd>
        </dl>
      </div>

      <div class="panel">
        <h2>Caregivers assigned</h2>
        @if ($patient->assignments->isEmpty())
          <div class="empty">Nobody assigned yet.</div>
        @else
          <div class="scroll">
            <table>
              <thead><tr><th>Caregiver</th><th>Role</th><th>From</th><th>Status</th></tr></thead>
              <tbody>
                @foreach ($patient->assignments as $a)
                  <tr>
                    <td><span class="code">{{ $a->caregiver?->code }}</span> {{ $a->caregiver?->user?->name }}</td>
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
        <h2>Consent</h2>
        <dl class="detail">
          <dt>Recorded</dt>
          <dd>{{ $patient->consent_given_at?->format('j F Y') ?: 'Not recorded' }}</dd>
          <dt>Given by</dt>
          <dd>{{ $patient->consent_by ?: 'The client' }}</dd>
        </dl>
      </div>

      <div class="panel">
        <h2>Family access</h2>
        @if ($patient->guardians->isEmpty())
          <div class="empty">No family members linked.</div>
        @else
          <div class="scroll">
            <table>
              <thead><tr><th>Name</th><th>Relationship</th><th>Can see</th></tr></thead>
              <tbody>
                @foreach ($patient->guardians as $g)
                  <tr>
                    <td>{{ $g->user?->name }} @if ($g->is_primary)<span class="pill active">Primary</span>@endif</td>
                    <td>{{ $g->relationship ?: '-' }}</td>
                    <td>
                      @if ($g->can_view_notes)<span class="pill">Notes</span>@endif
                      @if ($g->can_view_invoices)<span class="pill">Invoices</span>@endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      <div class="panel">
        <h2>Archive</h2>
        <div style="padding:16px 20px">
          <p style="margin:0 0 12px;color:var(--ink-soft);font-size:14px">
            Archiving hides the client from lists but keeps the record. Visit
            history is evidence and is never destroyed by this action.
          </p>
          <form method="POST" action="{{ route('admin.patients.destroy', $patient) }}"
                onsubmit="return confirm('Archive {{ $patient->code }}?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" type="submit">Archive client</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
