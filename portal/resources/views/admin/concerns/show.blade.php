@extends('layouts.app')
@section('title', 'Concern')

@php use App\Models\Concern; @endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ Concern::CATEGORIES[$concern->category] ?? 'Concern' }}</h1>
      <p>
        @if ($concern->patient)<a href="{{ route('admin.patients.show', $concern->patient) }}">{{ $concern->patient->name }}</a> &middot; @endif
        raised {{ $concern->created_at->format('j M Y, H:i') }} by {{ $concern->raisedBy?->name }}
        ({{ $concern->raisedBy?->role === 'guardian' ? 'family' : $concern->raisedBy?->role }})
      </p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.concerns.index') }}">All concerns</a>
  </div>

  @if ($errors->any())
    <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="cols">
    <div>
      <div class="panel">
        <h2>What was said</h2>
        <div class="prose">{!! nl2br(e($concern->detail)) !!}</div>
      </div>

      @if ($concern->shift)
        <div class="panel">
          <h2>The visit</h2>
          <dl class="detail">
            <dt>Shift</dt>
            <dd><a href="{{ route('admin.shifts.show', $concern->shift) }}">{{ $concern->shift->shift_date->format('D j M') }}, {{ $concern->shift->timeRange() }}</a></dd>
            <dt>Caregiver</dt><dd>{{ $concern->shift->caregiver()?->user?->name }}</dd>
          </dl>
        </div>
      @endif
    </div>

    <div>
      <div class="panel">
        <h2>Handle it</h2>
        <form class="inset" method="POST" action="{{ route('admin.concerns.update', $concern) }}">
          @csrf
          @method('PUT')
          <div class="field">
            <label for="assigned_to_id">Owner</label>
            <select id="assigned_to_id" name="assigned_to_id">
              <option value="">Nobody yet</option>
              @foreach ($staff as $s)
                <option value="{{ $s->id }}" @selected(old('assigned_to_id', $concern->assigned_to_id ?? auth()->id()) == $s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              @foreach (['open' => 'Open', 'investigating' => 'Being looked into', 'resolved' => 'Resolved', 'closed' => 'Closed, no action needed'] as $v => $l)
                <option value="{{ $v }}" @selected(old('status', $concern->status) === $v)>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="resolution">What was done</label>
            <textarea id="resolution" name="resolution" rows="4" maxlength="2000">{{ old('resolution', $concern->resolution) }}</textarea>
            <p class="hint">Needed to resolve or close it. Office only; the family sees only that it is resolved. Encrypted at rest.</p>
          </div>
          <button class="btn btn-primary" type="submit">Save</button>
        </form>
        @if ($concern->resolved_at)
          <p class="inset muted" style="padding-top:0">Resolved {{ $concern->resolved_at->format('j M Y, H:i') }}.</p>
        @endif
      </div>
    </div>
  </div>
@endsection
