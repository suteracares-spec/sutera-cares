@extends('layouts.app')
@section('title', $patient->exists ? 'Edit client' : 'New client')

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $patient->exists ? 'Edit ' . $patient->name : 'New client' }}</h1>
      <p>{{ $patient->exists ? $patient->code : 'Reference ' . $patient->code . ' is assigned on save.' }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form class="form" method="POST"
        action="{{ $patient->exists ? route('admin.patients.update', $patient) : route('admin.patients.store') }}">
    @csrf
    @if ($patient->exists)
      @method('PUT')
    @endif

    <fieldset>
      <legend>Who they are</legend>
      <div class="grid2">
        <div class="field">
          <label for="name">Full name *</label>
          <input id="name" name="name" value="{{ old('name', $patient->name) }}" required>
        </div>
        <div class="field">
          <label for="ic_number">IC number</label>
          <input id="ic_number" name="ic_number" value="{{ old('ic_number', $patient->ic_number) }}">
          <p class="hint">Encrypted at rest.</p>
        </div>
        <div class="field">
          <label for="dob">Date of birth</label>
          <input id="dob" name="dob" type="date" value="{{ old('dob', $patient->dob?->format('Y-m-d')) }}">
        </div>
        <div class="field">
          <label for="gender">Gender</label>
          <select id="gender" name="gender">
            <option value="">-</option>
            @foreach (['female', 'male', 'other'] as $g)
              <option value="{{ $g }}" @selected(old('gender', $patient->gender) === $g)>{{ ucfirst($g) }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Where they are</legend>
      <div class="field">
        <label for="address">Address</label>
        <textarea id="address" name="address">{{ old('address', $patient->address) }}</textarea>
      </div>
      <div class="grid2">
        <div class="field">
          <label for="area">Area or town</label>
          <input id="area" name="area" value="{{ old('area', $patient->area) }}">
          <p class="hint">Used to match caregivers by travel distance.</p>
        </div>
        <div class="field">
          <label for="postcode">Postcode</label>
          <input id="postcode" name="postcode" value="{{ old('postcode', $patient->postcode) }}">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Care context</legend>
      <div class="grid2">
        <div class="field">
          <label for="mobility_level">Mobility</label>
          <select id="mobility_level" name="mobility_level">
            <option value="">-</option>
            @php($levels = ['independent' => 'Walks independently', 'walks_with_aid' => 'Walks with stick or frame', 'wheelchair' => 'Wheelchair user', 'bed_bound' => 'Mostly bed-bound'])
            @foreach ($levels as $key => $label)
              <option value="{{ $key }}" @selected(old('mobility_level', $patient->mobility_level) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="languages">Languages spoken</label>
          <input id="languages" name="languages" value="{{ old('languages', $patient->languages) }}"
                 placeholder="e.g. Bahasa Malaysia, Hokkien">
        </div>
      </div>
      <div class="field">
        <label for="allergies">Allergies</label>
        <textarea id="allergies" name="allergies">{{ old('allergies', $patient->allergies) }}</textarea>
      </div>
      <div class="field">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes">{{ old('notes', $patient->notes) }}</textarea>
        <p class="hint">Encrypted at rest. Record what a caregiver needs to know, not everything you know.</p>
      </div>
    </fieldset>

    <fieldset>
      <legend>Consent and status</legend>
      <div class="grid2">
        <div class="field">
          <label for="consent_given_at">Consent recorded on</label>
          <input id="consent_given_at" name="consent_given_at" type="date"
                 value="{{ old('consent_given_at', $patient->consent_given_at?->format('Y-m-d')) }}">
          <p class="hint">PDPA: health data needs explicit, dated consent.</p>
        </div>
        <div class="field">
          <label for="consent_by">Consent given by</label>
          <input id="consent_by" name="consent_by" value="{{ old('consent_by', $patient->consent_by) }}"
                 placeholder="If not the client themselves">
        </div>
        <div class="field">
          <label for="status">Status *</label>
          <select id="status" name="status" required>
            @foreach (['enquiry', 'assessment', 'active', 'paused', 'closed'] as $s)
              <option value="{{ $s }}" @selected(old('status', $patient->status) === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </fieldset>

    <div class="actions">
      <button class="btn btn-primary" type="submit">{{ $patient->exists ? 'Save changes' : 'Create client' }}</button>
      <a class="btn btn-quiet" href="{{ route('admin.patients.index') }}">Cancel</a>
    </div>
  </form>
@endsection
