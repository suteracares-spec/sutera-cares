@extends('layouts.app')
@section('title', 'Caregivers')

@section('content')
  <div class="pagehead">
    <div><h1>Caregivers</h1><p>{{ $caregivers->total() }} {{ Str::plural('record', $caregivers->total()) }}</p></div>
    <div class="spacer"></div>
    <a class="btn btn-primary" href="{{ route('admin.caregivers.create') }}">New caregiver</a>
  </div>

  <form class="filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, code, area or skill">
    <select name="status">
      <option value="">Any status</option>
      @foreach (['applicant', 'vetting', 'active', 'inactive', 'left'] as $s)
        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <button class="btn btn-quiet" type="submit">Filter</button>
  </form>

  <div class="panel">
    @if ($caregivers->isEmpty())
      <div class="empty">No caregivers match. Create one, or convert a job application.</div>
    @else
      <div class="scroll">
        <table>
          <thead>
            <tr><th>Code</th><th>Name</th><th>Base area</th><th>Languages</th><th>Vetting</th><th>Status</th></tr>
          </thead>
          <tbody>
            @foreach ($caregivers as $c)
              <tr>
                <td class="code">{{ $c->code }}</td>
                <td><a href="{{ route('admin.caregivers.show', $c) }}">{{ $c->user?->name }}</a></td>
                <td>{{ $c->base_area ?: '-' }}</td>
                <td>{{ $c->languages ?: '-' }}</td>
                <td>
                  @if (! $c->right_to_work_verified)
                    <span class="pill risk">Right to work</span>
                  @elseif ($c->police_check_expires_at === null)
                    <span class="pill risk">No check</span>
                  @elseif ($c->police_check_expires_at->isPast())
                    <span class="pill risk">Expired</span>
                  @elseif ($c->policeCheckExpiringSoon())
                    <span class="pill assessment">{{ $c->police_check_expires_at->format('j M Y') }}</span>
                  @else
                    <span class="pill active">Current</span>
                  @endif
                </td>
                <td><span class="pill {{ $c->status }}">{{ ucfirst($c->status) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  {{ $caregivers->links() }}
@endsection
