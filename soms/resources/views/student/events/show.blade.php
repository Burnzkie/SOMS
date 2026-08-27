<x-layout :title="$event->title">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>{{ $event->title }}</h1>
    <p class="meta">{{ $event->date_start->format('M j') }} – {{ $event->date_end->format('M j, Y') }} · {{ $event->venue }}</p>
  </div>
  <a href="{{ route('student.events.index') }}" class="link-sm">← All events</a>
</div>

@if($event->description)
<div class="panel">
  <p style="font-size:13.5px; color:var(--text-muted); line-height:1.6;">{{ $event->description }}</p>
</div>
@endif

@php
  $statusBadge = fn ($status) => match($status) {
      'present' => 'approved',
      'absent'  => 'rejected',
      'late'    => 'pending',
      default   => null,
  };
@endphp

@forelse($event->eventDays as $day)
  <div class="panel">
    <div class="panel-head">
      <h3>{{ $day->label ?? $day->date->format('l, F j, Y') }}</h3>
    </div>
    @forelse($day->sessions as $session)
      @php
        $timeIn = $myAttendance->get($session->id . '_time_in');
        $timeOut = $myAttendance->get($session->id . '_time_out');
      @endphp
      <div class="queue-item">
        <div class="who">
          <b>{{ ucfirst($session->session_type) }}</b>
          <span>
            Time-in: {{ \Illuminate\Support\Carbon::parse($session->timein_start)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($session->timein_end)->format('g:i A') }}
            @if($session->timeout_start)
              &nbsp;·&nbsp; Time-out: {{ \Illuminate\Support\Carbon::parse($session->timeout_start)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($session->timeout_end)->format('g:i A') }}
            @endif
          </span>
        </div>
        <div class="queue-actions">
          @if($timeIn)
            <span class="badge {{ $statusBadge($timeIn->status) }}">In: {{ ucfirst($timeIn->status) }}</span>
          @else
            <span class="badge" style="background:var(--surface-2); color:var(--text-faint);">In: —</span>
          @endif
          @if($session->timeout_start)
            @if($timeOut)
              <span class="badge {{ $statusBadge($timeOut->status) }}">Out: {{ ucfirst($timeOut->status) }}</span>
            @else
              <span class="badge" style="background:var(--surface-2); color:var(--text-faint);">Out: —</span>
            @endif
          @endif
        </div>
      </div>
    @empty
      <p class="empty-note">No sessions scheduled for this day.</p>
    @endforelse
  </div>
@empty
  <div class="panel"><p class="empty-note">No schedule published for this event yet.</p></div>
@endforelse
@endslot

</x-layout>
