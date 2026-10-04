@extends('layouts.app')
@section('title', 'Office staff')

@section('content')
  <div class="pagehead">
    <div>
      <h1>Office staff</h1>
      <p>Coordinators run the day. Administrators also manage staff, the audit log and system updates.</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="cols">
    <div class="panel">
      <h2>Accounts</h2>
      <div class="scroll">
        <table>
          <thead><tr><th>Name</th><th>Role</th><th>Two-factor</th><th>Last signed in</th><th></th></tr></thead>
          <tbody>
            @foreach ($staff as $u)
              <tr>
                <td>{{ $u->name }}<br><span class="muted">{{ $u->email }}</span></td>
                <td>{{ ucfirst($u->role) }} <span class="pill {{ $u->status }}">{{ $u->status }}</span></td>
                <td>{!! $u->hasTwoFactor() ? '<span class="pill active">On</span>' : '<span class="pill risk">Not set up</span>' !!}</td>
                <td class="num">{{ $u->last_login_at?->format('j M Y') ?? 'Never' }}</td>
                <td class="rowactions">
                  @unless ($u->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.temp-password', $u) }}"
                          onsubmit="return confirm('Issue a new temporary password?')">@csrf
                      <button class="linkbtn plain" type="submit">New password</button></form>
                    @if ($u->hasTwoFactor())
                      <form method="POST" action="{{ route('admin.staff.reset-2fa', $u) }}"
                            onsubmit="return confirm('Reset two-factor? They set it up again at next sign-in.')">@csrf
                        <button class="linkbtn plain" type="submit">Reset two-factor</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.staff.status', $u) }}">@csrf
                      <button class="linkbtn" type="submit">{{ $u->status === 'suspended' ? 'Restore' : 'Suspend' }}</button></form>
                  @else
                    <span class="muted">You</span>
                  @endunless
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <h2>Add a staff member</h2>
      <form class="inset" method="POST" action="{{ route('admin.staff.store') }}">
        @csrf
        <div class="field">
          <label for="s_name">Name *</label>
          <input id="s_name" name="name" required value="{{ old('name') }}">
        </div>
        <div class="field">
          <label for="s_email">Email *</label>
          <input id="s_email" name="email" type="email" required value="{{ old('email') }}">
        </div>
        <div class="field">
          <label for="s_phone">Phone</label>
          <input id="s_phone" name="phone" value="{{ old('phone') }}">
        </div>
        <div class="field">
          <label for="s_role">Role *</label>
          <select id="s_role" name="role">
            <option value="coordinator">Coordinator</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Add and issue a temporary password</button>
      </form>
    </div>
  </div>
@endsection
