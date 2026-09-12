@extends('layouts.app')
@section('title', $area)
@section('content')
  <div class="pagehead"><div>
    <h1>{{ $area }}</h1>
    <p>Signed in as {{ auth()->user()->name }} ({{ auth()->user()->role }}).</p>
  </div></div>
  <div class="panel">
    <div class="empty">
      This area is not built yet. The office dashboard is the first slice;
      this one follows in a later phase.
    </div>
  </div>
@endsection
