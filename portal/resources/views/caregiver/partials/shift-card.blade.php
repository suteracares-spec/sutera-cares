@php $p = $shift->assignment->patient; @endphp
<a class="shiftcard {{ $shift->status }}" href="{{ route('caregiver.shifts.show', $shift) }}">
  <span class="when">
    @if ($showDate){{ $shift->shift_date->format('D j M') }} &middot; @endif{{ $shift->timeRange() }}
  </span>
  <span class="who">{{ $p->name }}</span>
  <span class="where">{{ $p->area ?: 'Area not recorded' }} &middot; {{ $shift->assignment->service?->name }}</span>
  @if ($shift->status !== 'scheduled')
    <span class="pill {{ $shift->status }}">{{ $shift->status === 'in_progress' ? 'Checked in' : str_replace('_', ' ', $shift->status) }}</span>
  @endif
</a>
