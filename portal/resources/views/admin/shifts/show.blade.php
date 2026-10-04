@extends('layouts.app')
@section('title', 'Shift ' . $shift->shift_date->format('j M') . ' · ' . $shift->assignment->patient->name)

@php $a = $shift->assignment; $log = $shift->visitLog; @endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $shift->shift_date->format('l j F') }}, {{ $shift->timeRange() }}</h1>
      <p>
        <a href="{{ route('admin.patients.show', $a->patient) }}">{{ $a->patient->name }}</a>
        &middot; {{ $a->service?->name }}
        &middot; <span class="pill {{ $shift->status }}">{{ str_replace('_', ' ', $shift->status) }}</span>
      </p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.assignments.show', $a) }}">Back to assignment</a>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @if (in_array($shift->status, ['cancelled', 'missed'], true))
    <div class="notice">{{ ucfirst($shift->status) }}: {{ $shift->cancel_reason }}</div>
  @endif

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Who</h2>
        <dl class="detail">
          <dt>Assigned</dt>
          <dd><a href="{{ route('admin.caregivers.show', $a->caregiver) }}">{{ $a->caregiver?->user?->name }}</a></dd>
          @if ($shift->covered_by_id)
            <dt>Covered by</dt>
            <dd><a href="{{ route('admin.caregivers.show', $shift->coveredBy) }}">{{ $shift->coveredBy?->user?->name }}</a></dd>
          @endif
        </dl>
      </div>

      <div class="panel">
        <h2>Visit record</h2>
        @if (! $log)
          <div class="empty">Nobody has checked in.</div>
        @else
          <dl class="detail">
            <dt>Checked in</dt><dd>{{ $log->check_in_at?->format('H:i, j M') ?? '-' }}
              @if ($log->check_in_lat) <span class="muted">(location recorded)</span> @endif</dd>
            <dt>Checked out</dt><dd>{{ $log->check_out_at?->format('H:i, j M') ?? 'Not yet' }}</dd>
            @if ($log->minutes_worked)
              <dt>Time on site</dt><dd>{{ intdiv($log->minutes_worked, 60) }}h {{ $log->minutes_worked % 60 }}m</dd>
            @endif
            <dt>Tasks done</dt>
            <dd>
              @forelse ($log->tasks_completed ?? [] as $task)
                <div>&check; {{ $task }}</div>
              @empty
                <span class="muted">None recorded</span>
              @endforelse
            </dd>
            <dt>Visit note</dt><dd>{!! $log->notes ? nl2br(e($log->notes)) : '<span class="muted">None</span>' !!}</dd>
            @if ($log->concern_flagged)
              <dt>Concern</dt><dd><span class="pill risk">Flagged</span> {{ $log->concern_detail }}</dd>
            @endif
          </dl>
        @endif
      </div>
    </div>

    <div>
      @if ($shift->status !== 'cancelled')
        <div class="panel">
          <h2>{{ $log ? 'Correct the visit record' : 'Record the visit by hand' }}</h2>
          <form class="inset" method="POST" action="{{ route('admin.shifts.correct', $shift) }}">
            @csrf
            <p class="muted">For when the phone failed or someone forgot to check out. The old times and your reason are kept in the audit log.</p>
            <div class="grid2">
              <div class="field">
                <label for="c_in">Arrived *</label>
                <input id="c_in" name="check_in_at" type="datetime-local" required
                       value="{{ ($log?->check_in_at ?? $shift->startsAt())->format('Y-m-d\TH:i') }}">
              </div>
              <div class="field">
                <label for="c_out">Left *</label>
                <input id="c_out" name="check_out_at" type="datetime-local" required
                       value="{{ ($log?->check_out_at ?? $shift->endsAt())->format('Y-m-d\TH:i') }}">
              </div>
            </div>
            <div class="field">
              <label for="c_reason">Why *</label>
              <input id="c_reason" name="reason" required maxlength="300" placeholder="e.g. Phone battery died; confirmed with the family">
            </div>
            <button class="btn btn-quiet" type="submit">Save record</button>
          </form>
        </div>
      @endif

      @if ($shift->status === 'scheduled' && $shift->endsAt()->isPast())
        <div class="panel">
          <h2>Nobody came</h2>
          <form class="inset" method="POST" action="{{ route('admin.shifts.missed', $shift) }}">
            @csrf
            <div class="field">
              <label for="missed_reason">What happened *</label>
              <input id="missed_reason" name="reason" required maxlength="300" placeholder="e.g. Caregiver ill, no relief found">
            </div>
            <button class="btn btn-danger" type="submit">Mark as missed</button>
          </form>
        </div>
      @endif

      @if ($shift->status === 'scheduled')
        <div class="panel">
          <h2>Relief cover</h2>
          <form class="inset" method="POST" action="{{ route('admin.shifts.cover', $shift) }}">
            @csrf
            <div class="field">
              <label for="covered_by_id">Send instead</label>
              <select id="covered_by_id" name="covered_by_id">
                <option value="">{{ $a->caregiver?->user?->name }} (assigned)</option>
                @foreach ($relief as $c)
                  <option value="{{ $c->id }}" @selected($shift->covered_by_id === $c->id)>{{ $c->user?->name }} ({{ $c->code }})</option>
                @endforeach
              </select>
              <p class="hint">For this shift only. Busy caregivers are refused.</p>
            </div>
            <button class="btn btn-quiet" type="submit">Save cover</button>
          </form>
        </div>

        <div class="panel">
          <h2>Move</h2>
          <form class="inset" method="POST" action="{{ route('admin.shifts.update', $shift) }}">
            @csrf
            @method('PUT')
            <div class="field">
              <label for="m_date">Day</label>
              <input id="m_date" name="shift_date" type="date" required value="{{ $shift->shift_date->format('Y-m-d') }}">
            </div>
            <div class="grid2">
              <div class="field">
                <label for="m_start">From</label>
                <input id="m_start" name="start_time" type="time" required value="{{ substr($shift->start_time, 0, 5) }}">
              </div>
              <div class="field">
                <label for="m_end">To</label>
                <input id="m_end" name="end_time" type="time" required value="{{ substr($shift->end_time, 0, 5) }}">
              </div>
            </div>
            <button class="btn btn-quiet" type="submit">Move shift</button>
          </form>
        </div>

        <div class="panel">
          <h2>Cancel</h2>
          <form class="inset" method="POST" action="{{ route('admin.shifts.cancel', $shift) }}">
            @csrf
            <div class="field">
              <label for="cancel_reason">Why *</label>
              <input id="cancel_reason" name="cancel_reason" required maxlength="300"
                     placeholder="e.g. Family away; client in hospital; public holiday">
            </div>
            <button class="btn btn-danger" type="submit">Cancel shift</button>
          </form>
        </div>
      @endif
    </div>
  </div>
@endsection
