@extends('layouts.app')
@section('title', 'Recovery codes')

@section('content')
  <div class="narrow">
    <div class="pagehead">
      <div>
        <h1>Two-factor sign-in is on</h1>
        <p>Save these recovery codes now. This is the only time they are shown.</p>
      </div>
    </div>

    <div class="form">
      <p>If you lose your phone, each of these lets you sign in once instead of a code from the app.
        Print them or write them down and keep them somewhere safe, not on the same phone.</p>
      <ul class="codes">
        @foreach ($codes as $code)<li>{{ $code }}</li>@endforeach
      </ul>
      <div class="actions">
        <button class="btn btn-quiet" type="button" onclick="window.print()">Print</button>
        <a class="btn btn-primary" href="{{ route(auth()->user()->homeRoute()) }}">I have saved them: continue</a>
      </div>
    </div>
  </div>
@endsection
