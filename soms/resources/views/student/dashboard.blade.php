<x-layout title="Dashboard">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋</h1>
    <p class="meta">Here's what's happening in your organization today.</p>
  </div>
  <p class="meta">{{ now()->format('F j, Y') }}<br>{{ now()->format('l') }}</p>
</div>

@if($unpaidFinesCount > 0)
<div class="banner warn">
  ₱ You have {{ $unpaidFinesCount }} unpaid {{ Str::plural('fine', $unpaidFinesCount) }} totaling ₱{{ number_format($unpaidFinesAmount, 2) }}. Pay in person at the Treasurer's office.
</div>
@endif

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon orange">📅</div>
    <div class="value">{{ $upcomingEvents->count() }}</div>
    <div class="label">Upcoming Events</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🪪</div>
    <div class="value"><a href="{{ route('student.qr.show') }}" class="link-sm" style="font-size:15px;">My QR</a></div>
    <div class="label">Attendance code</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">💲</div>
    <div class="value">{{ $unpaidFinesCount }}</div>
    <div class="label">Unpaid Fines</div>
    <a href="{{ route('student.fines.index') }}" class="link-sm" style="display:block; margin-top:8px;">View fines →</a>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">📣</div>
    <div class="value">{{ $recentAnnouncements->count() }}</div>
    <div class="label">Latest Announcements</div>
    <a href="{{ route('student.announcements.index') }}" class="link-sm" style="display:block; margin-top:8px;">View all →</a>
  </div>
</div>

<div class="dash-row">
  @php($feature = $upcomingEvents->first())
  @if($feature)
  <div class="feature-card">
    <div class="feature-bg" style="{{ $feature->image_url ? 'background-image:url(\'' . $feature->image_url . '\');' : '' }}"></div>
    <div class="feature-content">
      <div class="dash-tag">🏷 Upcoming Event</div>
      <h2>{{ $feature->title }}</h2>
      @if($feature->description)
      <p class="desc">{{ \Illuminate\Support\Str::limit($feature->description, 140) }}</p>
      @endif
      <div class="feature-meta">
        <span>📅 {{ $feature->date_start->format('F j, Y') }}@if(!$feature->date_start->isSameDay($feature->date_end)) – {{ $feature->date_end->format('F j, Y') }}@endif</span>
        @if($feature->venue)
        <span>📍 {{ $feature->venue }}</span>
        @endif
      </div>
      <a href="{{ route('student.events.show', $feature) }}" class="btn btn-primary" style="width:auto; padding:0 22px; display:inline-flex;">View Event Details →</a>
    </div>
  </div>
  @else
  <div class="empty-feature">No upcoming events right now.</div>
  @endif

  <div class="announce-card">
    <div class="dash-tag">📣 Latest Announcement <a href="{{ route('student.announcements.index') }}" class="view-all">View all</a></div>
    @php($announcement = $recentAnnouncements->first())
    @if($announcement)
      <h4>{{ $announcement->title }}</h4>
      <p class="body-text">{{ $announcement->body }}</p>
      <p class="when">{{ $announcement->created_at->diffForHumans() }}</p>
      <a href="{{ route('student.announcements.index') }}" class="link-sm">View all announcements →</a>
    @else
      <p class="body-text" style="-webkit-line-clamp:unset;">No announcements yet.</p>
    @endif
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Quick Access</h3></div>
  <div class="qa-grid">
    <a href="{{ route('student.events.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--primary-soft); color:var(--primary);">📅</div>
      <div class="qa-text"><b>Events</b><span>Browse upcoming and past events</span></div>
    </a>
    <a href="{{ route('student.qr.show') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--purple-soft); color:var(--purple);">🪪</div>
      <div class="qa-text"><b>My QR</b><span>Show for attendance scanning</span></div>
    </a>
    <a href="{{ route('student.fines.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--emerald-soft); color:var(--emerald);">💲</div>
      <div class="qa-text"><b>Fines</b><span>Check your fine history</span></div>
    </a>
    <a href="{{ route('student.announcements.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--amber-soft); color:var(--amber);">📣</div>
      <div class="qa-text"><b>Announcements</b><span>Read the latest updates</span></div>
    </a>
  </div>
</div>
@endslot

</x-layout>
