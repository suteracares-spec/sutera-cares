@extends('layouts.app')
@section('title', $assignment->patient->name . ' · ' . ($assignment->caregiver?->user?->name ?? 'Assignment'))

@php
  $ended = $assignment->status === 'ended';
  $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
@endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $assignment->service?->name ?? 'Assignment' }}</h1>
      <p>
        <a href="{{ route('admin.patients.show', $assignment->patient) }}">{{ $assignment->patient->name }}</a>
        &larr;
        <a href="{{ route('admin.caregivers.show', $assignment->caregiver) }}">{{ $assignment->caregiver?->user?->name }}</a>
        &middot; <span class="pill {{ $assignment->status }}">{{ $assignment->status === 'active' ? 'Confirmed' : ucfirst($assignment->status) }}</span>
        @if ($assignment->role === 'relief')<span class="pill">Relief</span>@endif
      </p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.schedule', ['client' => $assignment->patient_id]) }}">Client's week</a>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="cols">
    <div>
      @unless ($ended || $assignment->isOneOff())
        <div class="panel">
          <h2>Book shifts from a weekly pattern</h2>
          <form class="inset" method="POST" action="{{ route('admin.shifts.generate', $assignment) }}">
            @csrf
            <div class="field">
              <label>Days *</label>
              <div class="daypicker">
                @foreach ($days as $n => $label)
                  <label><input type="checkbox" name="weekdays[]" value="{{ $n }}"
                         @checked(in_array($n, old('weekdays', [1, 2, 3, 4, 5])))> {{ $label }}</label>
                @endforeach
              </div>
            </div>
            <div class="grid4">
              <div class="field">
                <label for="g_start">From *</label>
                <input id="g_start" name="start_time" type="time" required value="{{ old('start_time', '08:00') }}">
              </div>
              <div class="field">
                <label for="g_end">To *</label>
                <input id="g_end" name="end_time" type="time" required value="{{ old('end_time', '13:00') }}">
              </div>
              <div class="field">
                <label for="g_from">Starting *</label>
                <input id="g_from" name="from" type="date" required
                       value="{{ old('from', max($assignment->start_date, today())->format('Y-m-d')) }}">
              </div>
              <div class="field">
                <label for="g_until">Until *</label>
                <input id="g_until" name="until" type="date" required
                       value="{{ old('until', min($assignment->end_date ?? today()->addWeeks(4), today()->addWeeks(4))->format('Y-m-d')) }}">
              </div>
            </div>
            <p class="muted">Days already booked are skipped, and so are days the caregiver is busy elsewhere; you are told which.
              Up to 26 weeks at a time.</p>
            <button class="btn btn-primary" type="submit">Book shifts</button>
          </form>
        </div>
      @endunless

      <div class="panel">
        <h2>Upcoming shifts</h2>
        @if ($upcoming->isEmpty())
          <div class="empty">Nothing booked from today on.</div>
        @else
          <div class="scroll">
            <table>
              <thead><tr><th>Day</th><th>Time</th><th>Who</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @foreach ($upcoming as $shift)
                  <tr>
                    <td class="num">{{ $shift->shift_date->format('D j M') }}</td>
                    <td class="num">{{ $shift->timeRange() }}</td>
                    <td>
                      @if ($shift->covered_by_id)
                        {{ $shift->coveredBy?->user?->name }} <span class="pill">Covering</span>
                      @else
                        {{ $assignment->caregiver?->user?->name }}
                      @endif
                    </td>
                    <td><span class="pill {{ $shift->status }}">{{ str_replace('_', ' ', $shift->status) }}</span></td>
                    <td><a href="{{ route('admin.shifts.show', $shift) }}">Open</a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      @if ($recent->isNotEmpty())
        <div class="panel">
          <h2>Recent shifts</h2>
          <div class="scroll">
            <table>
              <thead><tr><th>Day</th><th>Time</th><th>Checked in</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @foreach ($recent as $shift)
                  <tr>
                    <td class="num">{{ $shift->shift_date->format('D j M') }}</td>
                    <td class="num">{{ $shift->timeRange() }}</td>
                    <td class="num">{{ $shift->visitLog?->check_in_at?->format('H:i') ?? '-' }}</td>
                    <td><span class="pill {{ $shift->status }}">{{ str_replace('_', ' ', $shift->status) }}</span></td>
                    <td><a href="{{ route('admin.shifts.show', $shift) }}">Open</a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

    <div>
      <div class="panel">
        <h2>Details</h2>
        <dl class="detail">
          <dt>Dates</dt>
          <dd>{{ $assignment->start_date->format('j M Y') }}
              @if ($assignment->isOneOff()) (one-off) @else &ndash; {{ $assignment->end_date?->format('j M Y') ?? 'open-ended' }} @endif</dd>
          <dt>Rate</dt>
          <dd>RM {{ number_format((float) $assignment->rate(), 2) }} / {{ $assignment->service?->unit ?? 'unit' }}</dd>
          <dt>Care plan</dt>
          <dd>
            @if ($plan = $assignment->patient->activeCarePlan())
              <a href="{{ route('admin.care-plans.show', $plan) }}">Version {{ $plan->version }}</a>
            @else
              <span class="muted">None active</span>
            @endif
          </dd>
          @if ($assignment->notes)
            <dt>Notes</dt><dd>{{ $assignment->notes }}</dd>
          @endif
        </dl>
      </div>

      @unless ($ended)
        @unless ($assignment->isOneOff())
          <div class="panel">
            <h2>Add a single shift</h2>
            <form class="inset" method="POST" action="{{ route('admin.shifts.store', $assignment) }}">
              @csrf
              <div class="field">
                <label for="s_date">Day *</label>
                <input id="s_date" name="shift_date" type="date" required value="{{ old('shift_date') }}">
              </div>
              <div class="grid2">
                <div class="field">
                  <label for="s_start">From *</label>
                  <input id="s_start" name="start_time" type="time" required value="{{ old('start_time', '08:00') }}">
                </div>
                <div class="field">
                  <label for="s_end">To *</label>
                  <input id="s_end" name="end_time" type="time" required value="{{ old('end_time', '13:00') }}">
                </div>
              </div>
              <button class="btn btn-quiet" type="submit">Add shift</button>
            </form>
          </div>
        @endunless

        <div class="panel">
          <h2>Status and rate</h2>
          <form class="inset" method="POST" action="{{ route('admin.assignments.update', $assignment) }}">
            @csrf
            @method('PUT')
            <div class="field">
              <label for="a_status">Status</label>
              <select id="a_status" name="status">
                <option value="active" @selected($assignment->status === 'active')>Confirmed</option>
                <option value="proposed" @selected($assignment->status === 'proposed')>Proposed</option>
              </select>
            </div>
            <div class="field">
              <label for="a_rate">Rate charged (RM)</label>
              <input id="a_rate" name="charge_rate" type="number" step="0.01" min="0" value="{{ $assignment->charge_rate }}">
              <p class="hint">Applies to shifts not yet invoiced.</p>
            </div>
            <div class="field">
              <label for="a_notes">Notes</label>
              <input id="a_notes" name="notes" maxlength="500" value="{{ $assignment->notes }}">
            </div>
            <button class="btn btn-quiet" type="submit">Save</button>
          </form>
        </div>

        <div class="panel">
          <h2>End this assignment</h2>
          <form class="inset" method="POST" action="{{ route('admin.assignments.end', $assignment) }}"
                onsubmit="return confirm('End this assignment and cancel the shifts booked after the last day?')">
            @csrf
            <div class="field">
              <label for="last_day">Last day *</label>
              <input id="last_day" name="last_day" type="date" required value="{{ today()->format('Y-m-d') }}">
            </div>
            <div class="field">
              <label for="reason">Why *</label>
              <input id="reason" name="reason" required maxlength="200" placeholder="e.g. Client moved to a care home">
            </div>
            <p class="muted">Shifts after the last day are cancelled. Past shifts and their visit records are kept.</p>
            <button class="btn btn-danger" type="submit">End assignment</button>
          </form>
        </div>
      @endunless
    </div>
  </div>
@endsection
