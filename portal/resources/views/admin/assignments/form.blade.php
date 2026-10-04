@extends('layouts.app')
@section('title', ($oneOff ? 'Book a visit' : 'Assign a caregiver') . ' · ' . $patient->name)

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $oneOff ? 'Book a one-off visit' : 'Assign a caregiver' }}</h1>
      <p>For <a href="{{ route('admin.patients.show', $patient) }}">{{ $patient->name }}</a>
         <span class="code">{{ $patient->code }}</span></p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @if (! $oneOff && ! $activePlan)
    <div class="notice">
      This client has no agreed care plan yet. Ongoing care cannot be assigned until one is active;
      appointment escorts and wellness visits can.
    </div>
  @endif

  @if ($caregivers->isEmpty())
    <div class="errors">
      No caregiver is placeable right now: every active caregiver needs right to work verified and a
      police check in date. <a href="{{ route('admin.caregivers.index') }}">Check vetting</a>.
    </div>
  @endif

  <form class="form" method="POST" action="{{ route('admin.assignments.store', $patient) }}">
    @csrf
    <input type="hidden" name="one_off" value="{{ $oneOff ? 1 : 0 }}">

    <fieldset>
      <legend>Who and what</legend>
      <div class="grid2">
        <div class="field">
          <label for="caregiver_id">{{ $oneOff ? 'Caregiver or therapist' : 'Caregiver' }} *</label>
          <select id="caregiver_id" name="caregiver_id" required>
            <option value="">Choose…</option>
            @foreach ($caregivers as $c)
              <option value="{{ $c->id }}" @selected(old('caregiver_id') == $c->id)>
                {{ $c->user?->name }} ({{ $c->code }}{{ $c->base_area ? ', ' . $c->base_area : '' }}{{ $c->gender ? ', ' . $c->gender : '' }})
              </option>
            @endforeach
          </select>
          <p class="hint">Only placeable caregivers are listed. Client area: {{ $patient->area ?: 'not recorded' }}; languages: {{ $patient->languages ?: 'not recorded' }}.</p>
        </div>
        <div class="field">
          <label for="service_id">Service *</label>
          <select id="service_id" name="service_id" required>
            <option value="">Choose…</option>
            @foreach ($services as $s)
              <option value="{{ $s->id }}" data-rate="{{ $s->base_rate }}" @selected(old('service_id') == $s->id)>
                {{ $s->name }} (RM {{ number_format($s->base_rate, 2) }} / {{ $s->unit }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="charge_rate">Rate charged to the client (RM)</label>
          <input id="charge_rate" name="charge_rate" type="number" step="0.01" min="0" value="{{ old('charge_rate') }}"
                 placeholder="List price">
          <p class="hint">Leave empty to charge the service's list price. Per hour, visit, day or session, as the service is.</p>
        </div>
        @unless ($oneOff)
          <div class="field">
            <label for="role">Role *</label>
            <select id="role" name="role">
              <option value="primary" @selected(old('role') === 'primary')>Primary caregiver</option>
              <option value="relief" @selected(old('role') === 'relief')>Named relief</option>
            </select>
            <p class="hint">A named relief already knows the client and covers when the primary cannot.</p>
          </div>
        @endunless
      </div>
    </fieldset>

    <fieldset>
      <legend>When</legend>
      <div class="grid2">
        @if ($oneOff)
          <div class="field">
            <label for="date">Date *</label>
            <input id="date" name="date" type="date" required value="{{ old('date', today()->addDay()->format('Y-m-d')) }}">
          </div>
          <div class="field"></div>
          <div class="field">
            <label for="start_time">Starts *</label>
            <input id="start_time" name="start_time" type="time" required value="{{ old('start_time', '10:00') }}">
          </div>
          <div class="field">
            <label for="end_time">Ends *</label>
            <input id="end_time" name="end_time" type="time" required value="{{ old('end_time', '11:00') }}">
          </div>
        @else
          <div class="field">
            <label for="start_date">Starts *</label>
            <input id="start_date" name="start_date" type="date" required
                   value="{{ old('start_date', $activePlan?->effective_from?->isFuture() ? $activePlan->effective_from->format('Y-m-d') : today()->format('Y-m-d')) }}">
          </div>
          <div class="field">
            <label for="end_date">Ends</label>
            <input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}">
            <p class="hint">Leave empty for an open-ended placement.</p>
          </div>
          <div class="field">
            <label for="status">Status *</label>
            <select id="status" name="status">
              <option value="active" @selected(old('status') === 'active')>Confirmed</option>
              <option value="proposed" @selected(old('status') === 'proposed')>Proposed, not yet confirmed with the family</option>
            </select>
          </div>
        @endif
      </div>
      <div class="field">
        <label for="notes">Notes for the office</label>
        <input id="notes" name="notes" maxlength="500" value="{{ old('notes') }}"
               placeholder="{{ $oneOff ? 'e.g. Ground-floor flat, park at the back' : 'e.g. Family asked for the same caregiver every weekday' }}">
      </div>
    </fieldset>

    <div class="actions">
      <button class="btn btn-primary" type="submit">{{ $oneOff ? 'Book visit' : 'Create assignment' }}</button>
      <a class="btn btn-quiet" href="{{ route('admin.patients.show', $patient) }}">Cancel</a>
    </div>
  </form>
@endsection
