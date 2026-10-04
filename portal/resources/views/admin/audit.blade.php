@extends('layouts.app')
@section('title', 'Audit log')

@section('content')
  <div class="pagehead">
    <div>
      <h1>Audit log</h1>
      <p>Every view of a client record, every change, every sign-in. Nothing here can be edited or deleted.</p>
    </div>
  </div>

  <form class="filters" method="GET" action="{{ route('admin.audit') }}">
    <select name="user" aria-label="Who">
      <option value="">Anyone</option>
      @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <select name="action" aria-label="Action">
      <option value="">Any action</option>
      @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ str_replace('_', ' ', $a) }}</option>@endforeach
    </select>
    <input name="subject" value="{{ request('subject') }}" placeholder="e.g. patient:12" aria-label="Record">
    <button class="btn btn-quiet" type="submit">Filter</button>
  </form>

  <div class="panel">
    @if ($entries->isEmpty())
      <div class="empty">No entries.</div>
    @else
      <div class="scroll">
        <table>
          <thead><tr><th>When</th><th>Who</th><th>Did</th><th>To</th><th>Detail</th><th>From</th></tr></thead>
          <tbody>
            @foreach ($entries as $e)
              <tr>
                <td class="num">{{ $e->created_at?->format('j M Y, H:i:s') }}</td>
                <td>{{ $e->user?->name ?? 'Nobody signed in' }}</td>
                <td>{{ str_replace('_', ' ', $e->action) }}</td>
                <td>
                  @if ($e->subject_type)
                    <a href="{{ route('admin.audit', ['subject' => $e->subject_type . ':' . $e->subject_id]) }}">{{ str_replace('_', ' ', $e->subject_type) }} #{{ $e->subject_id }}</a>
                  @endif
                </td>
                <td>{{ $e->detail }}</td>
                <td class="code">{{ $e->ip_address }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
  {{ $entries->links() }}
@endsection
