@extends('layouts.app')
@section('title', ($guardian->exists ? 'Edit family member' : 'Add family member') . ' · ' . $patient->name)

@php
  $switches = [
      'is_primary'          => ['Main contact', 'The person the office calls first. Only one per client; ticking this moves it from whoever had it.'],
      'is_bill_payer'       => ['Pays the bills', 'Invoices are addressed to them. Usually also needs "Can see invoices".'],
      'can_view_notes'      => ['Can read visit notes', 'Notes describe personal care in detail. Off unless the client, or whoever holds their consent, agrees.'],
      'can_view_invoices'   => ['Can see invoices', 'Amounts charged and payments made.'],
      'can_request_changes' => ['Can request care plan changes', 'Requests reach the office; they do not change the plan by themselves.'],
  ];
@endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $guardian->exists ? 'Edit ' . $guardian->user?->name : 'Add family member' }}</h1>
      <p>For <a href="{{ route('admin.patients.show', $patient) }}">{{ $patient->name }}</a>
         <span class="code">{{ $patient->code }}</span></p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form class="form" method="POST"
        action="{{ $guardian->exists ? route('admin.guardians.update', $guardian) : route('admin.guardians.store', $patient) }}">
    @csrf
    @if ($guardian->exists)
      @method('PUT')
    @endif

    <fieldset>
      <legend>Person and login</legend>
      <div class="grid2">
        <div class="field">
          <label for="name">Full name *</label>
          <input id="name" name="name" value="{{ old('name', $guardian->user?->name) }}" required>
        </div>
        <div class="field">
          <label for="relationship">Relationship to the client</label>
          <input id="relationship" name="relationship" value="{{ old('relationship', $guardian->relationship) }}"
                 placeholder="e.g. Daughter, Son, Nephew" maxlength="60">
        </div>
        <div class="field">
          <label for="email">Email *</label>
          <input id="email" name="email" type="email" value="{{ old('email', $guardian->user?->email) }}" required>
          <p class="hint">
            How they sign in to the family portal.
            @unless ($guardian->exists)
              If they already have a family login for another client, use the same email and they are linked to both.
            @else
              Changing it changes their sign-in, for every client they are linked to.
            @endunless
          </p>
        </div>
        <div class="field">
          <label for="phone">Phone / WhatsApp</label>
          <input id="phone" name="phone" value="{{ old('phone', $guardian->user?->phone) }}">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>What they can see and do</legend>
      @foreach ($switches as $field => [$label, $hint])
        <div class="field">
          <label style="font-weight:600">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" style="width:auto;margin-right:8px"
                   @checked(old($field, $guardian->{$field}))>
            {{ $label }}
          </label>
          <p class="hint">{{ $hint }}</p>
        </div>
      @endforeach
    </fieldset>

    <div class="actions">
      <button class="btn btn-primary" type="submit">{{ $guardian->exists ? 'Save changes' : 'Add family member' }}</button>
      <a class="btn btn-quiet" href="{{ route('admin.patients.show', $patient) }}">Cancel</a>
    </div>
  </form>

  @if ($guardian->exists)
    <div style="max-width:860px;margin-top:22px">
      @include('admin.partials.issue-password', ['user' => $guardian->user])
    </div>
    <div class="panel" style="max-width:860px">
      <h2>Remove from this client</h2>
      <form class="inset" method="POST" action="{{ route('admin.guardians.destroy', $guardian) }}"
            onsubmit="return confirm('Remove this family member from this client?')">
        @csrf
        @method('DELETE')
        <p class="muted">
          They lose access to this client straight away. If this is the only client they are linked to,
          their sign-in is suspended as well.
        </p>
        <button class="btn btn-danger" type="submit">Remove access</button>
      </form>
    </div>
  @endif
@endsection
