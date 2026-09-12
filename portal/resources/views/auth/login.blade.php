<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · Sutera Care Provider</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>
<div class="signin">
  <div class="signin-card">
    <div class="mark">
      <svg viewBox="0 0 120 120" aria-hidden="true" style="width:36px;height:36px">
        <path d="M60,104 C 26,78 12,52 22,34 C 30,20 48,22 60,42 C 72,22 90,20 98,34 C 108,52 94,78 60,104 Z" fill="none" stroke="#1C6B62" stroke-width="8" stroke-linejoin="round"/>
        <path d="M38,62 h12 l6,-13 l8,26 l6,-13 h12" fill="none" stroke="#D9A441" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Sutera <b>Care Provider</b></span>
    </div>

    <h1>Sign in</h1>
    <p class="sub">Staff, caregivers and family members</p>

    @if ($errors->any())
      <div class="errors">
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label style="font-weight:600;font-size:14px">
          <input type="checkbox" name="remember" value="1" style="width:auto;margin-right:7px">
          Keep me signed in on this device
        </label>
      </div>
      <button class="btn btn-primary" type="submit">Sign in</button>
    </form>

    <p class="signin-foot">
      This portal holds patient information. Do not sign in on a shared or
      public computer.<br>
      <a href="https://provider.suteracares.org/">Back to the website</a>
    </p>
  </div>
</div>
</body>
</html>
