@extends('layouts.app')
@section('title', 'Set up two-factor sign-in')

@section('content')
  <div class="narrow">
    <div class="pagehead">
      <div>
        <h1>Set up two-factor sign-in</h1>
        <p>Office accounts can read every client record, so they need a code from your phone as well as a password.</p>
      </div>
    </div>

    @if ($errors->any())
      <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="form">
      <ol class="steps">
        <li>
          Install an authenticator app on your phone if you do not have one:
          <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong>, free from your app store.
        </li>
        <li>
          In the app, choose <em>Add account</em>, then <em>Enter a setup key</em>, and type:
          <p class="secret-key">{{ $readable }}</p>
          <p class="muted">Account name: anything you like, e.g. "Sutera". Type of key: time-based.
            Reading this on your phone? <a href="{{ $uri }}">Tap here to add it</a> instead.</p>
        </li>
        <li>
          Type the six-digit code the app now shows:
          <form method="POST" action="{{ route('two-factor.confirm') }}" autocomplete="off">
            @csrf
            <div class="field" style="margin-top:10px">
              <input name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus
                     class="codebox" placeholder="123456" aria-label="Six-digit code">
            </div>
            <button class="btn btn-primary" type="submit">Turn on two-factor sign-in</button>
          </form>
        </li>
      </ol>
      <p class="muted">Keep this page private: the key above is what makes your codes.</p>
    </div>
  </div>
@endsection
