@extends('layouts.app')
@section('title', 'System')

@section('content')
  <div class="pagehead">
    <div>
      <h1>System</h1>
      <p>Database updates and production settings. Administrators only.</p>
    </div>
  </div>

  <div class="cols">
    <div>
      <div class="panel">
        <h2>Database updates</h2>
        @if ($pending === [])
          <div class="empty">The database is up to date.</div>
        @else
          <div class="inset">
            <p>
              The uploaded version of the portal needs {{ count($pending) }}
              database {{ str('update')->plural(count($pending)) }}. Until they are applied,
              the pages that use them will show errors.
            </p>
            <ul class="mono-list">
              @foreach ($pending as $name)<li>{{ $name }}</li>@endforeach
            </ul>
            <p class="muted">
              Updates only add tables and columns; no existing record is changed or removed.
              Take a backup in cPanel first if you want to be able to step back.
            </p>
            <form method="POST" action="{{ route('admin.system.migrate') }}"
                  onsubmit="return confirm('Apply the database updates now?')">
              @csrf
              <button class="btn btn-primary" type="submit">Apply {{ count($pending) }} {{ str('update')->plural(count($pending)) }}</button>
            </form>
          </div>
        @endif
        @if (session('migrate_output'))
          <pre class="output">{{ session('migrate_output') }}</pre>
        @endif
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Production settings</h2>
        <div class="scroll">
          <table>
            <tbody>
              @foreach ($checks as [$label, $value, $ok, $advice])
                <tr>
                  <td><strong>{{ $label }}</strong>@unless ($ok)<br><span class="muted">{{ $advice }}</span>@endunless</td>
                  <td>{{ $value }}</td>
                  <td>@if ($ok)<span class="pill active">OK</span>@else<span class="pill risk">Fix</span>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
