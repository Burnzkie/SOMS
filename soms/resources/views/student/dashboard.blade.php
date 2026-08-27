<x-layout title="Dashboard">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <p class="meta">{{ now()->format('l, F j, Y') }}</p>
  </div>
</div>

@if($unpaidFinesCount > 0)
<div class="banner warn">
  ₱ You have {{ $unpaidFinesCount }} unpaid {{ Str::plural('fine', $unpaidFinesCount) }} totaling ₱{{ number_format($unpaidFinesAmount, 2) }}. Pay in person at the Treasurer's office.
</div>
@endif

<div class="panel-row" style="display:grid; grid-template-columns:1.4fr 1fr; gap:18px;">
  <div class="panel">
    <div class="panel-head">
      <h3>Upcoming events</h3>
      <a href="{{ route('student.events.index') }}" class="link-sm">View all</a>
    </div>
    @forelse($upcomingEvents as $event)
      <div class="queue-item">
        <div class="who">
          <b>{{ $event->title }}</b>
          <span>{{ $event->date_start->format('M j') }} – {{ $event->date_end->format('M j, Y') }} · {{ $event->venue }}</span>
        </div>
        <a href="{{ route('student.events.show', $event) }}" class="link-sm" style="margin-left:auto;">View</a>
      </div>
    @empty
      <p class="empty-note">No upcoming events right now.</p>
    @endforelse
  </div>

  <div class="panel">
    <div class="panel-head">
      <h3>Latest announcements</h3>
      <a href="{{ route('student.announcements.index') }}" class="link-sm">View all</a>
    </div>
    @forelse($recentAnnouncements as $announcement)
      <div class="queue-item">
        <div class="who">
          <b>{{ $announcement->title }}</b>
          <span>{{ $announcement->created_at->diffForHumans() }}</span>
        </div>
      </div>
    @empty
      <p class="empty-note">No announcements yet.</p>
    @endforelse
  </div>
</div>
@endslot

</x-layout>
