@extends('layouts.app')
@section('title', 'Schedule, week of ' . $week->format('j M'))

@section('content')
  <div class="pagehead">
    <div>
      <h1>Week of {{ $week->format('j F Y') }}</h1>
      <p>{{ $shifts->flatten()->where('status', '!=', 'cancelled')->count() }} shifts booked</p>
    </div>
    <div class="spacer"></div>
    @php $q = array_filter(['caregiver' => $caregiverId, 'client' => $patientId]); @endphp
    <a class="btn btn-quiet" href="{{ route('admin.schedule', $q + ['week' => $week->copy()->subWeek()->toDateString()]) }}">&larr; Previous</a>
    <a class="btn btn-quiet" href="{{ route('admin.schedule', $q) }}">This week</a>
    <a class="btn btn-quiet" href="{{ route('admin.schedule', $q + ['week' => $week->copy()->addWeek()->toDateString()]) }}">Next &rarr;</a>
  </div>

  <form class="filters" method="GET" action="{{ route('admin.schedule') }}">
    <input type="hidden" name="week" value="{{ $week->toDateString() }}">
    <select name="caregiver" aria-label="Caregiver" onchange="this.form.submit()">
      <option value="">All caregivers</option>
      @foreach ($caregivers as $c)
        <option value="{{ $c->id }}" @selected($caregiverId === $c->id)>{{ $c->user?->name }}</option>
      @endforeach
    </select>
    <select name="client" aria-label="Client" onchange="this.form.submit()">
      <option value="">All clients</option>
      @foreach ($clients as $p)
        <option value="{{ $p->id }}" @selected($patientId === $p->id)>{{ $p->name }}</option>
      @endforeach
    </select>
    <noscript><button class="btn btn-quiet" type="submit">Filter</button></noscript>
  </form>

  <div class="week">
    @foreach ($days as $day)
      @php $list = $shifts->get($day->toDateString(), collect()); @endphp
      <section @class(['day', 'today' => $day->isToday()])>
        <h2>{{ $day->format('D') }} <span>{{ $day->format('j M') }}</span></h2>
        @forelse ($list as $shift)
          @php $carer = $shift->caregiver(); @endphp
          <a href="{{ route('admin.shifts.show', $shift) }}" @class(['slot', $shift->status])>
            <span class="time">{{ $shift->timeRange() }}</span>
            <span class="who">{{ $shift->assignment?->patient?->name }}</span>
            <span class="by">{{ $carer?->user?->name ?? 'Nobody' }}@if ($shift->covered_by_id) (cover)@endif</span>
            @if ($shift->status !== 'scheduled')<span class="state">{{ str_replace('_', ' ', $shift->status) }}</span>@endif
          </a>
        @empty
          <p class="none">-</p>
        @endforelse
      </section>
    @endforeach
  </div>
@endsection
