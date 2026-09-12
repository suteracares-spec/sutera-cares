@extends('layouts.app')
@section('title', 'Enquiries')

@section('content')
  <div class="pagehead">
    <div><h1>Enquiries</h1><p>Everything the website sends us</p></div>
  </div>

  <form class="filters" method="GET">
    <select name="status">
      <option value="">Any status</option>
      @foreach (['new', 'contacted', 'assessment_booked', 'converted', 'declined', 'lost'] as $s)
        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
      @endforeach
    </select>
    <button class="btn btn-quiet" type="submit">Filter</button>
  </form>

  <div class="panel">
    <h2>Care enquiries</h2>
    @if ($enquiries->isEmpty())
      <div class="empty">Nothing here. The quote form on the website writes to this table.</div>
    @else
      <div class="scroll">
        <table>
          <thead>
            <tr><th>Received</th><th>From</th><th>Needs care</th><th>Area</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            @foreach ($enquiries as $e)
              <tr>
                <td class="num">{{ $e->created_at?->format('j M, H:i') }}</td>
                <td>
                  {{ $e->client_name }}<br>
                  <span class="code">{{ $e->client_phone }}</span>
                  @if ($e->client_relationship)
                    <br><span style="font-size:13px;color:var(--ink-faint)">{{ $e->client_relationship }}</span>
                  @endif
                </td>
                <td>
                  {{ $e->patient_name ?: '-' }}@if ($e->patient_age), {{ $e->patient_age }}@endif
                  @if ($e->needs)
                    <br><span style="font-size:13px;color:var(--ink-soft)">{{ Str::limit($e->needs, 90) }}</span>
                  @endif
                </td>
                <td>{{ $e->patient_area ?: '-' }}</td>
                <td>
                  <form method="POST" action="{{ route('admin.enquiries.status', $e) }}">
                    @csrf @method('PATCH')
                    <select name="status" onchange="this.form.submit()" style="font-size:13px;padding:5px 8px">
                      @foreach (['new', 'contacted', 'assessment_booked', 'converted', 'declined', 'lost'] as $s)
                        <option value="{{ $s }}" @selected($e->status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                      @endforeach
                    </select>
                  </form>
                </td>
                <td>
                  @if ($e->patient_id)
                    <a class="btn btn-quiet" href="{{ route('admin.patients.show', $e->patient_id) }}">Open client</a>
                  @else
                    <form method="POST" action="{{ route('admin.enquiries.convert', $e) }}">
                      @csrf
                      <button class="btn btn-primary" type="submit">Make client</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  {{ $enquiries->links() }}

  <div class="panel">
    <h2>Recent job applications</h2>
    @if ($applications->isEmpty())
      <div class="empty">Nothing here. The careers form on the website writes to this table.</div>
    @else
      <div class="scroll">
        <table>
          <thead>
            <tr><th>Received</th><th>Name</th><th>Applying for</th><th>Area</th><th>Experience</th><th>Status</th></tr>
          </thead>
          <tbody>
            @foreach ($applications as $a)
              <tr>
                <td class="num">{{ $a->created_at?->format('j M') }}</td>
                <td>{{ $a->name }}<br><span class="code">{{ $a->phone }}</span></td>
                <td>{{ $a->role_applied ?: '-' }}</td>
                <td>{{ $a->area ?: '-' }}</td>
                <td class="num">{{ $a->years_experience !== null ? $a->years_experience . ' yr' : '-' }}</td>
                <td><span class="pill {{ $a->status }}">{{ ucfirst($a->status) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection
