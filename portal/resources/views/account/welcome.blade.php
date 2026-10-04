@extends('layouts.app')
@section('title', 'Choose your password')

@section('content')
  <div class="narrow">
    <div class="pagehead">
      <div>
        <h1>Welcome, {{ $user->name }}</h1>
        <p>You signed in with a temporary password. Choose your own to continue.</p>
      </div>
    </div>

    @if ($errors->any())
      <div class="errors">
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form class="form" method="POST" action="{{ route('account.choose-password') }}" autocomplete="off">
      @csrf
      <div class="field">
        <label for="password">New password</label>
        <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" autofocus>
        <p class="hint">
          At least 12 characters, and checked against known data breaches. Three or four
          unrelated words is strong and easy to remember, e.g. <em>mango lantern river seven</em>.
        </p>
      </div>
      <div class="field">
        <label for="password_confirmation">Repeat it</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
      </div>
      <div class="actions">
        <button class="btn btn-primary" type="submit">Set password and continue</button>
      </div>
    </form>
  </div>
@endsection
