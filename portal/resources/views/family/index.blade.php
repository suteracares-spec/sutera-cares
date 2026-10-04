@extends('layouts.app')
@section('title', 'Family')

@section('content')
  <div class="phone">
    <h1 class="hello">Hello, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
    <p class="muted">Choose who you would like to see.</p>

    @forelse ($links as $link)
      <a class="shiftcard" href="{{ route('guardian.clients.show', $link->patient) }}">
        <span class="who">{{ $link->patient->name }}</span>
        <span class="where">{{ $link->relationship ?: 'Family member' }}</span>
      </a>
    @empty
      <p class="card-empty">Nobody is linked to your account yet. Your coordinator can set this up.</p>
    @endforelse
  </div>
@endsection
