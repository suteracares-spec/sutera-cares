@extends('layouts.app')
@section('title', 'Two-factor code')

@section('content')
  <div class="narrow">
    <div class="pagehead">
      <div>
        <h1>Enter your code</h1>
        <p>Open your authenticator app and type the six-digit code for Sutera Care Provider.</p>
      </div>
    </div>

    @if ($errors->any())
      <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form class="form" method="POST" action="{{ route('two-factor.verify') }}" autocomplete="off">
      @csrf
      <div class="field">
        <input name="code" inputmode="numeric" maxlength="20" required autofocus class="codebox"
               placeholder="123456" aria-label="Code">
        <p class="hint">Lost your phone? Type one of your recovery codes instead.</p>
      </div>
      <div class="actions">
        <button class="btn btn-primary" type="submit">Continue</button>
      </div>
    </form>
  </div>
@endsection
