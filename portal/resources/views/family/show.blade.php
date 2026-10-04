@extends('layouts.app')
@section('title', $patient->name)

@php use App\Models\CarePlanTask; @endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>{{ $patient->name }}</h1>
      <p>{{ today()->format('l j F') }}</p>
    </div>
    @if (auth()->user()->guardianLinks()->count() > 1)
      <div class="spacer"></div>
      <a class="btn btn-quiet" href="{{ route('guardian.dashboard') }}">Someone else</a>
    @endif
  </div>

  @if ($errors->any())
    <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Coming up this week</h2>
        @if ($coming->isEmpty())
          <div class="empty">No visits booked in the next seven days.</div>
        @else
          <ul class="agenda">
            @foreach ($coming as $shift)
              @php $carer = $shift->caregiver(); @endphp
              <li>
                <span class="d">{{ $shift->shift_date->isToday() ? 'Today' : ($shift->shift_date->isTomorrow() ? 'Tomorrow' : $shift->shift_date->format('D j M')) }}</span>
                <span class="t">{{ $shift->timeRange() }}</span>
                <span class="w">{{ $carer?->user?->name ?? 'To be confirmed' }}
                  @if ($shift->status === 'in_progress')<span class="pill in_progress">There now</span>@endif
                  <small>{{ $shift->assignment->service?->name }}</small></span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      <div class="panel">
        <h2>The last two weeks</h2>
        @if ($visits->isEmpty())
          <div class="empty">No visits recorded yet.</div>
        @else
          @foreach ($visits as $shift)
            @php $log = $shift->visitLog; @endphp
            <div class="visit">
              <div class="visit-head">
                <strong>{{ $shift->shift_date->format('D j M') }}</strong>
                <span>{{ $shift->caregiver()?->user?->name }}</span>
                @if ($shift->status === 'missed')
                  <span class="pill missed">Missed</span>
                @elseif ($log?->check_in_at)
                  <span class="muted">Arrived {{ $log->check_in_at->format('H:i') }}@if ($log->check_out_at), left {{ $log->check_out_at->format('H:i') }}@endif</span>
                @endif
              </div>
              @if ($shift->status === 'missed')
                <p class="muted">{{ $shift->cancel_reason }}</p>
              @endif
              @if ($log?->tasks_completed)
                <p class="done">&check; {{ implode(' · ', $log->tasks_completed) }}</p>
              @endif
              @if ($link->can_view_notes && $log?->notes)
                <p class="note">{!! nl2br(e($log->notes)) !!}</p>
              @endif
            </div>
          @endforeach
          @unless ($link->can_view_notes)
            <p class="inset muted">Visit notes are not shared with your account. Ask your coordinator if you would like to see them.</p>
          @endunless
        @endif
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Tell us something</h2>
        <div class="inset">
          @include('family.partials.concern-form', [
              'action' => route('guardian.clients.concern', $patient),
              'canRequestChange' => $link->can_request_changes,
          ])
        </div>
        @if ($concerns->isNotEmpty())
          <ul class="mini">
            @foreach ($concerns as $c)
              <li><span>{{ $c->created_at->format('j M') }}: {{ \App\Models\Concern::CATEGORIES[$c->category] ?? $c->category }}</span>
                  <span class="pill {{ $c->isOpen() ? 'scheduled' : 'completed' }}">{{ $c->isOpen() ? 'With the office' : 'Resolved' }}</span></li>
            @endforeach
          </ul>
        @endif
      </div>

      @if ($plan)
        <div class="panel">
          <h2>What the caregiver does</h2>
          <ul class="mini">
            @foreach ($plan->tasks as $task)
              <li><span>{{ $task->description }}</span><span class="muted">{{ CarePlanTask::FREQUENCIES[$task->frequency] ?? '' }}</span></li>
            @endforeach
          </ul>
          <p class="inset muted" style="padding-top:0">Care plan agreed {{ $plan->agreed_at?->format('j M Y') }}{{ $plan->agreed_by ? ' by ' . $plan->agreed_by : '' }}.</p>
        </div>
      @endif

      @if ($link->can_view_invoices)
        <div class="panel">
          <h2>Invoices</h2>
          @if ($invoices->isEmpty())
            <div class="empty">No invoices yet.</div>
          @else
            <ul class="mini">
              @foreach ($invoices as $inv)
                <li>
                  <a href="{{ route('guardian.invoices.show', $inv) }}" target="_blank">{{ $inv->number }}</a>
                  <span>RM {{ number_format((float) $inv->total, 2) }}</span>
                  <span class="pill {{ $inv->displayStatus() }}">{{ $inv->statusLabel() }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      @endif
    </div>
  </div>
@endsection
