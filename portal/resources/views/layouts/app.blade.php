<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Portal') · Sutera Care Provider</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>

<header class="topbar">
  <a class="mark" href="{{ route(auth()->user()->homeRoute()) }}">
    <svg viewBox="0 0 120 120" aria-hidden="true">
      <path d="M60,104 C 26,78 12,52 22,34 C 30,20 48,22 60,42 C 72,22 90,20 98,34 C 108,52 94,78 60,104 Z" fill="none" stroke="#1C6B62" stroke-width="8" stroke-linejoin="round"/>
      <path d="M38,62 h12 l6,-13 l8,26 l6,-13 h12" fill="none" stroke="#D9A441" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span>Sutera <b>Care Provider</b></span>
  </a>

  @auth
  <nav class="topnav">
    @if (auth()->user()->isStaff())
      <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard')])>Dashboard</a>
      <a href="{{ route('admin.patients.index') }}" @class(['on' => request()->routeIs('admin.patients.*')])>Clients</a>
    @endif
  </nav>

  <div class="who">
    <span class="name">{{ auth()->user()->name }}</span>
    <span class="role">{{ ucfirst(auth()->user()->role) }}</span>
    <form method="POST" action="{{ route('logout') }}">@csrf
      <button type="submit">Sign out</button>
    </form>
  </div>
  @endauth
</header>

<main class="shell">
  @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
  @endif

  @yield('content')
</main>

</body>
</html>
