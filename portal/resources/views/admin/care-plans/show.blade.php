@extends('layouts.app')
@section('title', 'Care plan v' . $plan->version . ' · ' . $plan->patient->name)

@php
  use App\Models\CarePlanTask;
@endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>Care plan &middot; version {{ $plan->version }}</h1>
      <p><a href="{{ route('admin.patients.show', $plan->patient) }}">{{ $plan->patient->name }}</a>
         <span class="code">{{ $plan->patient->code }}</span> &middot;
         <span class="pill {{ $plan->pillClass() }}">{{ ucfirst($plan->status) }}</span></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-quiet" href="{{ route('admin.patients.show', $plan->patient) }}">Back to client</a>
    @if ($plan->isDraft())
      <a class="btn btn-primary" href="{{ route('admin.care-plans.edit', $plan) }}">Edit draft</a>
    @elseif ($plan->status === 'active')
      <form method="POST" action="{{ route('admin.care-plans.store', $plan->patient) }}">
        @csrf
        <button class="btn btn-primary" type="submit">Revise this plan</button>
      </form>
    @endif
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @if ($plan->status === 'superseded')
    <div class="notice">
      This version is history. It applied from {{ $plan->effective_from->format('j M Y') }}
      to {{ $plan->effective_to?->format('j M Y') ?? 'its replacement' }}.
      @if ($current = $history->firstWhere('status', 'active'))
        The current plan is <a href="{{ route('admin.care-plans.show', $current) }}">version {{ $current->version }}</a>.
      @endif
    </div>
  @endif

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Tasks</h2>
        @if ($plan->tasks->isEmpty())
          <div class="empty">No tasks yet. @if ($plan->isDraft())<a href="{{ route('admin.care-plans.edit', $plan) }}">Add them</a>.@endif</div>
        @else
          @foreach ($plan->tasks->groupBy('category') as $category => $tasks)
            <h3 class="group">{{ CarePlanTask::CATEGORIES[$category] ?? $category }}</h3>
            <ul class="tasklist">
              @foreach ($tasks as $task)
                <li>
                  <span>{{ $task->description }}</span>
                  <span class="when">
                    {{ CarePlanTask::FREQUENCIES[$task->frequency] ?? $task->frequency }}@if ($task->time_of_day !== 'any'),
                    {{ strtolower(CarePlanTask::TIMES[$task->time_of_day] ?? $task->time_of_day) }}@endif
                  </span>
                </li>
              @endforeach
            </ul>
          @endforeach
        @endif
      </div>

      <div class="panel">
        <h2>How this person likes things done</h2>
        <div class="prose">{!! $plan->notes ? nl2br(e($plan->notes)) : '<span class="muted">Nothing recorded.</span>' !!}</div>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>About this version</h2>
        <dl class="detail">
          <dt>Takes effect</dt><dd>{{ $plan->effective_from->format('j M Y') }}</dd>
          @if ($plan->effective_to)
            <dt>Ended</dt><dd>{{ $plan->effective_to->format('j M Y') }}</dd>
          @endif
          <dt>Agreed by</dt><dd>{{ $plan->agreed_by ?: 'Not yet agreed' }}</dd>
          @if ($plan->agreed_at)
            <dt>Agreed on</dt><dd>{{ $plan->agreed_at->format('j M Y') }}</dd>
          @endif
          <dt>Written by</dt><dd>{{ $plan->author?->name ?? 'Unknown' }}</dd>
        </dl>
      </div>

      @if ($plan->isDraft())
        <div class="panel">
          <h2>Activate</h2>
          <form class="inset" method="POST" action="{{ route('admin.care-plans.activate', $plan) }}">
            @csrf
            <p class="muted">
              Once activated this version is what caregivers work from, and it can no longer be edited.
              @if ($history->firstWhere('status', 'active'))
                The current version is kept as history.
              @endif
            </p>
            @unless ($plan->patient->hasConsent())
              <p class="err">This client has no recorded consent, so the plan cannot be activated yet.</p>
            @endunless
            <div class="field">
              <label for="agreed_by">Agreed by *</label>
              <input id="agreed_by" name="agreed_by" value="{{ old('agreed_by') }}" required
                     placeholder="e.g. Puan Aminah, or her daughter Nor Hayati">
            </div>
            <div class="field">
              <label for="agreed_at">Agreed on *</label>
              <input id="agreed_at" name="agreed_at" type="date" required
                     value="{{ old('agreed_at', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}">
            </div>
            <button class="btn btn-primary" type="submit">Activate version {{ $plan->version }}</button>
          </form>
        </div>

        <div class="panel">
          <h2>Discard draft</h2>
          <form class="inset" method="POST" action="{{ route('admin.care-plans.destroy', $plan) }}"
                onsubmit="return confirm('Discard draft version {{ $plan->version }}?')">
            @csrf
            @method('DELETE')
            <p class="muted">Only drafts can be discarded. Agreed versions are kept.</p>
            <button class="btn btn-danger" type="submit">Discard draft</button>
          </form>
        </div>
      @endif

      <div class="panel">
        <h2>All versions</h2>
        <div class="scroll">
          <table>
            <thead><tr><th>Version</th><th>From</th><th>Status</th></tr></thead>
            <tbody>
              @foreach ($history as $v)
                <tr @class(['here' => $v->is($plan)])>
                  <td><a href="{{ route('admin.care-plans.show', $v) }}">v{{ $v->version }}</a></td>
                  <td class="num">{{ $v->effective_from->format('j M Y') }}</td>
                  <td><span class="pill {{ $v->pillClass() }}">{{ ucfirst($v->status) }}</span></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
