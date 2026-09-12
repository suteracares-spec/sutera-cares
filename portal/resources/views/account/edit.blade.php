@extends('layouts.app')
@section('title', 'Your account')

@section('content')
  <div class="pagehead">
    <div>
      <h1>Your account</h1>
      <p>Signed in as {{ $user->email }} &middot;
         <span class="pill {{ $user->role }}">{{ ucfirst($user->role) }}</span></p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Change password</h2>
        <div style="padding:22px 20px">
          <form method="POST" action="{{ route('account.password') }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="field">
              <label for="current_password">Current password</label>
              <input id="current_password" name="current_password" type="password"
                     required autocomplete="current-password">
            </div>

            <div class="field">
              <label for="password">New password</label>
              <input id="password" name="password" type="password"
                     required minlength="12" autocomplete="new-password">
              <p class="hint">
                At least 12 characters, and checked against known data breaches.
                Three or four unrelated words is both stronger and easier to remember
                than a short one with symbols in it.
              </p>
            </div>

            <div class="field">
              <label for="password_confirmation">Repeat the new password</label>
              <input id="password_confirmation" name="password_confirmation" type="password"
                     required minlength="12" autocomplete="new-password">
            </div>

            <div class="actions">
              <button class="btn btn-primary" type="submit">Change password</button>
            </div>

            <p class="hint" style="margin-top:14px">
              Changing your password signs out every other device you are signed in on.
            </p>
          </form>
        </div>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Your details</h2>
        <div style="padding:22px 20px">
          <form method="POST" action="{{ route('account.details') }}">
            @csrf
            @method('PUT')

            <div class="field">
              <label for="name">Name</label>
              <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
              <p class="hint">This is also your sign-in name.</p>
            </div>

            <div class="field">
              <label for="phone">Phone</label>
              <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            </div>

            <div class="field">
              <label for="locale">Language</label>
              <select id="locale" name="locale">
                @php($languages = ['en' => 'English', 'ms' => 'Bahasa Malaysia', 'zh' => '中文'])
                @foreach ($languages as $code => $label)
                  <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="actions">
              <button class="btn btn-quiet" type="submit">Save details</button>
            </div>
          </form>
        </div>
      </div>

      <div class="panel">
        <h2>Sign-in activity</h2>
        <dl class="detail">
          <dt>Last signed in</dt>
          <dd>{{ $user->last_login_at?->format('j M Y, H:i') ?? 'This is your first session' }}</dd>
          <dt>Account status</dt>
          <dd><span class="pill {{ $user->status }}">{{ ucfirst($user->status) }}</span></dd>
        </dl>
      </div>
    </div>
  </div>
@endsection
