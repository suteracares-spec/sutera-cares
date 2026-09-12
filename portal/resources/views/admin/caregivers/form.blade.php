@extends('layouts.app')
@section('title', $caregiver->exists ? 'Edit caregiver' : 'New caregiver')

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $caregiver->exists ? 'Edit ' . $caregiver->user?->name : 'New caregiver' }}</h1>
      <p>{{ $caregiver->exists ? $caregiver->code : 'Code ' . $caregiver->code . ' is assigned on save.' }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form class="form" method="POST"
        action="{{ $caregiver->exists ? route('admin.caregivers.update', $caregiver) : route('admin.caregivers.store') }}">
    @csrf
    @if ($caregiver->exists)
      @method('PUT')
    @endif

    <fieldset>
      <legend>Person and login</legend>
      <div class="grid2">
        <div class="field">
          <label for="name">Full name *</label>
          <input id="name" name="name" value="{{ old('name', $caregiver->user?->name) }}" required>
        </div>
        <div class="field">
          <label for="email">Email *</label>
          <input id="email" name="email" type="email" value="{{ old('email', $caregiver->user?->email) }}" required>
          <p class="hint">This is how they sign in to the portal.</p>
        </div>
        <div class="field">
          <label for="phone">Phone / WhatsApp</label>
          <input id="phone" name="phone" value="{{ old('phone', $caregiver->user?->phone) }}">
        </div>
        <div class="field">
          <label for="ic_number">IC number</label>
          <input id="ic_number" name="ic_number" value="{{ old('ic_number', $caregiver->ic_number) }}">
          <p class="hint">Encrypted at rest.</p>
        </div>
        <div class="field">
          <label for="gender">Gender</label>
          <select id="gender" name="gender">
            <option value="">-</option>
            @foreach (['female', 'male', 'other'] as $g)
              <option value="{{ $g }}" @selected(old('gender', $caregiver->gender) === $g)>{{ ucfirst($g) }}</option>
            @endforeach
          </select>
          <p class="hint">Some clients can only be assisted by a caregiver of the same gender.</p>
        </div>
        <div class="field">
          <label for="dob">Date of birth</label>
          <input id="dob" name="dob" type="date" value="{{ old('dob', $caregiver->dob?->format('Y-m-d')) }}">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Matching</legend>
      <div class="grid2">
        <div class="field">
          <label for="base_area">Base area</label>
          <input id="base_area" name="base_area" value="{{ old('base_area', $caregiver->base_area) }}">
        </div>
        <div class="field">
          <label for="max_travel_km">Will travel up to (km)</label>
          <input id="max_travel_km" name="max_travel_km" type="number" min="0" max="500"
                 value="{{ old('max_travel_km', $caregiver->max_travel_km) }}">
        </div>
        <div class="field">
          <label for="languages">Languages spoken</label>
          <input id="languages" name="languages" value="{{ old('languages', $caregiver->languages) }}"
                 placeholder="e.g. Bahasa Malaysia, English, Tamil">
        </div>
        <div class="field">
          <label for="hourly_rate">Pay rate (RM/hour)</label>
          <input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0"
                 value="{{ old('hourly_rate', $caregiver->hourly_rate) }}">
          <p class="hint">What we pay them, not what the client is charged.</p>
        </div>
      </div>
      <div class="field">
        <label for="skills">Skills and experience</label>
        <textarea id="skills" name="skills">{{ old('skills', $caregiver->skills) }}</textarea>
        <p class="hint">e.g. dementia care, hoisting and transfers, post-natal, massage.</p>
      </div>
      <div class="field">
        <label style="font-weight:600">
          <input type="hidden" name="has_own_transport" value="0">
          <input type="checkbox" name="has_own_transport" value="1" style="width:auto;margin-right:8px"
                 @checked(old('has_own_transport', $caregiver->has_own_transport))>
          Has own transport
        </label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Vetting</legend>
      <div class="grid2">
        <div class="field">
          <label for="police_check_expires_at">Police check valid until</label>
          <input id="police_check_expires_at" name="police_check_expires_at" type="date"
                 value="{{ old('police_check_expires_at', $caregiver->police_check_expires_at?->format('Y-m-d')) }}">
          <p class="hint">The dashboard warns 60 days before this date.</p>
        </div>
        <div class="field">
          <label for="status">Status *</label>
          <select id="status" name="status" required>
            @foreach (['applicant', 'vetting', 'active', 'inactive', 'left'] as $s)
              <option value="{{ $s }}" @selected(old('status', $caregiver->status) === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
          <p class="hint">Inactive or left suspends their portal access.</p>
        </div>
      </div>
      <div class="field">
        <label style="font-weight:600">
          <input type="hidden" name="right_to_work_verified" value="0">
          <input type="checkbox" name="right_to_work_verified" value="1" style="width:auto;margin-right:8px"
                 @checked(old('right_to_work_verified', $caregiver->right_to_work_verified))>
          Right to work verified
        </label>
        <p class="hint">A caregiver is not placeable until this is ticked and the police check is current.</p>
      </div>
    </fieldset>

    <div class="actions">
      <button class="btn btn-primary" type="submit">{{ $caregiver->exists ? 'Save changes' : 'Create caregiver' }}</button>
      <a class="btn btn-quiet" href="{{ route('admin.caregivers.index') }}">Cancel</a>
    </div>
  </form>
@endsection
