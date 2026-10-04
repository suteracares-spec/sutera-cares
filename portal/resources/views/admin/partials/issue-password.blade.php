{{-- Issue a temporary password for $user. Shown on caregiver, family and client pages. --}}
<div class="panel">
  <h2>Sign-in</h2>
  <form class="inset" method="POST" action="{{ route('admin.users.temp-password', $user) }}"
        onsubmit="return confirm('Issue a new temporary password? Any password they have now stops working.')">
    @csrf
    <p class="muted">
      @if ($user->status === 'invited')
        Not set up yet. Issue a temporary password to get them started.
      @elseif ($user->status === 'suspended')
        Sign-in suspended. A new password takes effect if access is restored.
      @else
        Active{{ $user->last_login_at ? ', last signed in ' . $user->last_login_at->format('j M Y') : '' }}.
        Use this if they have forgotten their password.
      @endif
    </p>
    <button class="btn btn-quiet" type="submit">{{ $user->status === 'invited' ? 'Set up sign-in' : 'Issue a temporary password' }}</button>
  </form>
</div>
