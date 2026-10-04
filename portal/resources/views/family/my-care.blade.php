@extends('layouts.app')
@section('title', 'My care')

@section('content')
  <div class="phone">
    <h1 class="hello">Hello, {{ $patient->name }}</h1>
    <p class="muted">{{ today()->format('l j F') }}</p>

    <h2 class="phone-h">Who is coming</h2>
    @forelse ($coming as $shift)
      <div class="shiftcard {{ $shift->status }}">
        <span class="when">{{ $shift->shift_date->isToday() ? 'Today' : ($shift->shift_date->isTomorrow() ? 'Tomorrow' : $shift->shift_date->format('l j M')) }}, {{ $shift->timeRange() }}</span>
        <span class="who">{{ $shift->caregiver()?->user?->name ?? 'To be confirmed' }}</span>
        <span class="where">{{ $shift->assignment->service?->name }}</span>
      </div>
    @empty
      <p class="card-empty">No visits booked in the next seven days.</p>
    @endforelse

    <h2 class="phone-h">Tell us something</h2>
    @if ($errors->any())
      <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @include('family.partials.concern-form', ['action' => route('patient.concern')])
  </div>
@endsection
