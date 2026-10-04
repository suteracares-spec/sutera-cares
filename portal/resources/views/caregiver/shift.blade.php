@extends('layouts.app')
@section('title', $patient->name)

@php
  use App\Models\CarePlanTask;
  use App\Models\Concern;
  $log = $shift->visitLog;
@endphp

@section('content')
  <div class="phone">
    <a class="back" href="{{ route('caregiver.dashboard') }}">&larr; My shifts</a>

    <h1 class="hello">{{ $patient->name }}</h1>
    <p class="muted">{{ $shift->shift_date->format('l j F') }} &middot; {{ $shift->timeRange() }} &middot; {{ $shift->assignment->service?->name }}</p>

    @if ($errors->any())
      <div class="errors">
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    {{-- ---- Before: check in ---- --}}
    @if ($shift->status === 'scheduled')
      @if ($canCheckIn)
        <form method="POST" action="{{ route('caregiver.shifts.check-in', $shift) }}" id="checkin">
          @csrf
          <input type="hidden" name="lat">
          <input type="hidden" name="lng">
          <button class="btn btn-primary btn-big" type="submit">I have arrived: check in</button>
          <p class="muted center">Your location is recorded if your phone allows it.</p>
        </form>
      @else
        <p class="notice">{{ $whyNot }}</p>
      @endif
    @elseif ($shift->status === 'cancelled')
      <p class="notice">This shift was cancelled. {{ $shift->cancel_reason }}</p>
    @elseif ($shift->status === 'completed')
      <p class="flash">Done. Checked in {{ $log?->check_in_at?->format('H:i') }}, out {{ $log?->check_out_at?->format('H:i') }}.</p>
    @endif

    {{-- ---- During: tasks, note, check out ---- --}}
    @if ($shift->status === 'in_progress')
      <p class="flash">Checked in at {{ $log->check_in_at->format('H:i') }}.</p>

      <form method="POST" action="{{ route('caregiver.shifts.check-out', $shift) }}" id="visit" data-draft="visit-{{ $shift->id }}">
        @csrf
        @if ($plan && $plan->tasks->isNotEmpty())
          <h2 class="phone-h">Tasks</h2>
          <div class="ticks">
            @foreach ($plan->tasks as $task)
              <label class="tick">
                <input type="checkbox" name="tasks[]" value="{{ $task->id }}" @checked(in_array($task->id, old('tasks', [])))>
                <span>
                  {{ $task->description }}
                  <small>{{ CarePlanTask::FREQUENCIES[$task->frequency] ?? '' }}@if ($task->time_of_day !== 'any'), {{ strtolower(CarePlanTask::TIMES[$task->time_of_day]) }}@endif</small>
                </span>
              </label>
            @endforeach
          </div>
        @endif

        <h2 class="phone-h">Visit note</h2>
        <textarea name="notes" rows="5" required
                  placeholder="How were they today? Eating, mood, anything new. What should the family or the next caregiver know?">{{ old('notes') }}</textarea>

        <label class="tick concern">
          <input type="hidden" name="concern_flagged" value="0">
          <input type="checkbox" name="concern_flagged" value="1" id="flag" @checked(old('concern_flagged'))>
          <span>I am worried about something and the office should know</span>
        </label>
        <div id="concern-fields" @if (! old('concern_flagged')) hidden @endif>
          <select name="concern_category" aria-label="What kind of concern">
            @foreach (Concern::CATEGORIES as $value => $label)
              <option value="{{ $value }}" @selected(old('concern_category') === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <textarea name="concern_detail" rows="3" maxlength="600"
                    placeholder="What happened? If anyone is in danger, call 999 first.">{{ old('concern_detail') }}</textarea>
        </div>

        <button class="btn btn-primary btn-big" type="submit">Check out and save</button>
        <p class="muted center">What you type is kept on this phone until it is saved, in case the signal drops.</p>
      </form>
    @endif

    {{-- ---- What to know about this person ---- --}}
    <h2 class="phone-h">About the client</h2>
    <dl class="facts">
      <dt>Address</dt>
      <dd>{{ $patient->address ?: 'Ask the office' }} {{ $patient->postcode }}
        @if ($patient->address)
          <br><a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($patient->address . ' ' . $patient->postcode) }}" target="_blank" rel="noopener">Open in Maps</a>
        @endif
      </dd>
      <dt>Mobility</dt><dd>{{ $patient->mobility_level ? ucfirst(str_replace('_', ' ', $patient->mobility_level)) : 'Not recorded' }}</dd>
      <dt>Languages</dt><dd>{{ $patient->languages ?: 'Not recorded' }}</dd>
      <dt>Allergies</dt><dd @class(['warn' => $patient->allergies])>{{ $patient->allergies ?: 'None recorded' }}</dd>
    </dl>

    @if ($plan?->notes)
      <h2 class="phone-h">How they like things done</h2>
      <div class="card-text">{!! nl2br(e($plan->notes)) !!}</div>
    @endif

    @if ($shift->status !== 'in_progress' && $plan && $plan->tasks->isNotEmpty())
      <h2 class="phone-h">Care plan</h2>
      <ul class="plainlist">
        @foreach ($plan->tasks as $task)<li>{{ $task->description }}</li>@endforeach
      </ul>
    @endif

    <p class="muted center" style="margin-top:28px">
      Caregivers do not give injections, dress wounds, manage IV lines or catheters, or change doses.
      If someone needs that, call the office.
    </p>
  </div>

  <script>
    (function () {
      // Check-in: try for a location for up to six seconds, then go anyway.
      var checkin = document.getElementById('checkin');
      if (checkin && navigator.geolocation) {
        var sent = false;
        checkin.addEventListener('submit', function (e) {
          if (sent) return;
          e.preventDefault();
          var button = checkin.querySelector('button');
          button.disabled = true;
          button.textContent = 'Checking in…';
          var go = function () { if (!sent) { sent = true; checkin.submit(); } };
          navigator.geolocation.getCurrentPosition(function (pos) {
            checkin.lat.value = pos.coords.latitude.toFixed(7);
            checkin.lng.value = pos.coords.longitude.toFixed(7);
            go();
          }, go, { timeout: 6000, maximumAge: 60000 });
          setTimeout(go, 7000);
        });
      }

      // Concern fields appear when the box is ticked.
      var flag = document.getElementById('flag');
      var fields = document.getElementById('concern-fields');
      if (flag) flag.addEventListener('change', function () { fields.hidden = !flag.checked; });

      // Keep the visit draft on the phone until it is saved.
      var visit = document.getElementById('visit');
      var key = visit && visit.dataset.draft;
      var store = null;
      try { store = window.localStorage; } catch (e) {}
      if (visit && store) {
        var saved = null;
        try { saved = JSON.parse(store.getItem(key) || 'null'); } catch (e) {}
        if (saved && !visit.notes.value) {
          visit.notes.value = saved.notes || '';
          (saved.tasks || []).forEach(function (id) {
            var box = visit.querySelector('input[name="tasks[]"][value="' + id + '"]');
            if (box) box.checked = true;
          });
        }
        visit.addEventListener('input', function () {
          var tasks = Array.prototype.map.call(visit.querySelectorAll('input[name="tasks[]"]:checked'), function (b) { return b.value; });
          try { store.setItem(key, JSON.stringify({ notes: visit.notes.value, tasks: tasks })); } catch (e) {}
        });
      }
    })();
  </script>
@endsection
