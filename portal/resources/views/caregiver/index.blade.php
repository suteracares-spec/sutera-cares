@extends('layouts.app')
@section('title', 'My shifts')

@section('content')
  <div class="phone">
    <h1 class="hello">Hello, {{ \Illuminate\Support\Str::before($caregiver->user->name, ' ') }}</h1>
    <p class="muted">{{ today()->format('l j F') }}</p>

    @if ($unfinished->isNotEmpty())
      <h2 class="phone-h">Not checked out</h2>
      @foreach ($unfinished as $shift)
        @include('caregiver.partials.shift-card', ['shift' => $shift, 'showDate' => true])
      @endforeach
    @endif

    <h2 class="phone-h">Today</h2>
    @forelse ($today as $shift)
      @include('caregiver.partials.shift-card', ['shift' => $shift, 'showDate' => false])
    @empty
      <p class="card-empty">No shifts today.</p>
    @endforelse

    <h2 class="phone-h">Next 7 days</h2>
    @forelse ($coming as $shift)
      @include('caregiver.partials.shift-card', ['shift' => $shift, 'showDate' => true])
    @empty
      <p class="card-empty">Nothing booked yet. The office will add your shifts here.</p>
    @endforelse
  </div>

  <script>
    // Visit drafts are kept on the phone while a visit is open. Once a
    // visit is saved its draft is no longer needed: drop every draft that
    // does not belong to a visit still in progress.
    (function () {
      var open = @json($today->merge($unfinished)->where('status', 'in_progress')->pluck('id')->map(fn ($id) => 'visit-' . $id)->values());
      try {
        Object.keys(window.localStorage).forEach(function (key) {
          if (key.indexOf('visit-') === 0 && open.indexOf(key) === -1) window.localStorage.removeItem(key);
        });
      } catch (e) {}
    })();
  </script>
@endsection
