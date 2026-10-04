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

      @php
        $activePlan = $patient->carePlans->firstWhere('status', 'active');
        $draftPlan = $patient->carePlans->firstWhere('status', 'draft');
      @endphp
      <div class="panel">
        <h2>Care plan</h2>
        @if ($patient->carePlans->isEmpty())
          <div class="empty">
            <p style="margin:0 0 12px">No care plan yet. It sets out what the caregiver does on each visit.</p>
            <form method="POST" action="{{ route('admin.care-plans.store', $patient) }}">
              @csrf
              <button class="btn btn-primary" type="submit">Start care plan</button>
            </form>
          </div>
        @else
          <dl class="detail">
            <dt>Current</dt>
            <dd>
              @if ($activePlan)
                <a href="{{ route('admin.care-plans.show', $activePlan) }}">Version {{ $activePlan->version }}</a>
                &middot; {{ $activePlan->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $activePlan->tasks_count) }}
                &middot; from {{ $activePlan->effective_from->format('j M Y') }}
              @else
                <span class="muted">None agreed yet</span>
              @endif
            </dd>
            @if ($activePlan)
              <dt>Agreed by</dt><dd>{{ $activePlan->agreed_by }}, {{ $activePlan->agreed_at?->format('j M Y') }}</dd>
            @endif
            @if ($draftPlan)
              <dt>In draft</dt>
              <dd>
                <a href="{{ route('admin.care-plans.edit', $draftPlan) }}">Version {{ $draftPlan->version }}</a>
                &middot; {{ $draftPlan->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $draftPlan->tasks_count) }}
                <span class="pill new">Not yet agreed</span>
              </dd>
            @endif
            @if ($patient->carePlans->count() > 1)
              <dt>History</dt>
              <dd>
                @foreach ($patient->carePlans->where('status', 'superseded') as $old)
                  <a href="{{ route('admin.care-plans.show', $old) }}">v{{ $old->version }}</a>@unless ($loop->last), @endunless
                @endforeach
              </dd>
            @endif
          </dl>
          @if ($activePlan && ! $draftPlan)
            <form class="inset" method="POST" action="{{ route('admin.care-plans.store', $patient) }}">
              @csrf
              <button class="btn btn-quiet" type="submit">Revise care plan</button>
            </form>
          @endif
        @endif
      </div>

      <div class="panel">
        <h2 class="withaction">Caregivers and visits
          <span>
            <a class="btn btn-quiet btn-small" href="{{ route('admin.assignments.create', $patient) }}">Assign caregiver</a>
            <a class="btn btn-quiet btn-small" href="{{ route('admin.assignments.create', [$patient, 'type' => 'visit']) }}">Book a visit</a>
          </span></h2>
        @if ($patient->assignments->isEmpty())
          <div class="empty">Nobody assigned yet.</div>
        @else
          <div class="scroll">
            <table>
              <thead><tr><th>Caregiver</th><th>Service</th><th>Dates</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @foreach ($patient->assignments->sortByDesc('start_date') as $a)
                  <tr>
                    <td>{{ $a->caregiver?->user?->name }}@if ($a->role === 'relief') <span class="pill">Relief</span>@endif</td>
                    <td>{{ $a->service?->name ?? '-' }}</td>
                    <td class="num">{{ $a->start_date->format('j M Y') }}@unless ($a->isOneOff()) &ndash; {{ $a->end_date?->format('j M Y') ?? 'ongoing' }}@endunless</td>
                    <td><span class="pill {{ $a->status }}">{{ $a->status === 'active' ? 'Confirmed' : ucfirst($a->status) }}</span></td>
                    <td><a href="{{ route('admin.assignments.show', $a) }}">Open</a></td>
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
        <h2 class="withaction">Family access
          <a class="btn btn-quiet btn-small" href="{{ route('admin.guardians.create', $patient) }}">Add</a></h2>
        @if ($patient->guardians->isEmpty())
          <div class="empty">No family members linked.</div>
        @else
          <div class="scroll">
            <table>
              <thead><tr><th>Name</th><th>Can see</th><th></th></tr></thead>
              <tbody>
                @foreach ($patient->guardians as $g)
                  <tr>
                    <td>
                      {{ $g->user?->name }} @if ($g->is_primary)<span class="pill active">Main contact</span>@endif
                      <br><span class="muted">{{ $g->relationship ?: 'Relationship not recorded' }}@if ($g->user?->status !== 'active') &middot; {{ $g->user?->status === 'invited' ? 'not signed in yet' : 'sign-in suspended' }}@endif</span>
                    </td>
                    <td>
                      @if ($g->can_view_notes)<span class="pill">Notes</span>@endif
                      @if ($g->can_view_invoices)<span class="pill">Invoices</span>@endif
                      @unless ($g->can_view_notes || $g->can_view_invoices)<span class="muted">Schedule only</span>@endunless
                    </td>
                    <td><a href="{{ route('admin.guardians.edit', $g) }}">Edit</a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      @if ($patient->user)
        @include('admin.partials.issue-password', ['user' => $patient->user])
      @else
        <div class="panel">
          <h2>Client's own sign-in</h2>
          <form class="inset" method="POST" action="{{ route('admin.patients.sign-in', $patient) }}">
            @csrf
            <p class="muted">Optional. Gives the client a small view of who is coming and a way to raise a concern.
              Most clients never need one; their family's sign-in is set up under Family access.</p>
            <div class="field">
              <label for="client_email">Client's email</label>
              <input id="client_email" name="email" type="email" required value="{{ old('email') }}">
            </div>
            <button class="btn btn-quiet" type="submit">Create sign-in</button>
          </form>
        </div>
      @endif

      <div class="panel">
        <h2 class="withaction">Invoices
          <a class="btn btn-quiet btn-small" href="{{ route('admin.invoices.index', ['client' => $patient->id, 'show' => 'all']) }}">All</a></h2>
        <form class="inset" method="POST" action="{{ route('admin.invoices.generate') }}">
          @csrf
          <input type="hidden" name="client" value="{{ $patient->id }}">
          <div class="field">
            <label for="bill_month">Bill a month</label>
            <input id="bill_month" type="month" name="month" value="{{ today()->subMonthNoOverflow()->format('Y-m') }}">
          </div>
          <button class="btn btn-quiet" type="submit">Draft invoice</button>
        </form>
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
