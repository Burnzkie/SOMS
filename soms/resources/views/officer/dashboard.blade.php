<x-layout title="Officer Dashboard">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('styles')
<style>
.tier-badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:12px;
  font-weight:700;
  padding:5px 12px;
  border-radius:99px;
  background:var(--primary-soft);
  color:var(--primary);
  margin-top:6px;
}
</style>
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋</h1>
    <p class="meta">Here's what's happening in your organization today.</p>
    @if($position)
      <div class="tier-badge">{{ $position->position_title }}</div>
    @endif
  </div>
  <p class="meta">{{ now()->format('F j, Y') }}<br>{{ now()->format('l') }}</p>
</div>

@if(session('status'))
<div class="banner success">{{ session('status') }}</div>
@endif

@if($isTreasurer)
<div class="banner warn">💰 As Treasurer, you have access to fine management — Fines is in the sidebar.</div>
@endif

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon orange">📅</div>
    <div class="value">{{ $stats['upcoming_events'] }}</div>
    <div class="label">Upcoming Events</div>
    @if($permissions['manage_events'] || $permissions['manage_attendance'])
    <a href="{{ route('officer.events.index') }}" class="link-sm" style="display:block; margin-top:8px;">View all events →</a>
    @endif
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">👥</div>
    <div class="value">{{ $stats['active_members'] }}</div>
    <div class="label">Active Members</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">💲</div>
    <div class="value">{{ $stats['pending_fines'] }}</div>
    <div class="label">Pending Fines</div>
    @if($isTreasurer)
    <a href="{{ route('officer.fines.index') }}" class="link-sm" style="display:block; margin-top:8px;">View fines →</a>
    @endif
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">📣</div>
    <div class="value">{{ $stats['new_announcements'] }}</div>
    <div class="label">New Announcements</div>
    @if($permissions['draft_announcements'] || $permissions['manage_announcements'])
    <a href="{{ route('officer.announcements.index') }}" class="link-sm" style="display:block; margin-top:8px;">View all →</a>
    @endif
  </div>
</div>

<div class="dash-row">
  @if($upcomingEvent)
  <div class="feature-card">
    <div class="feature-bg" style="{{ $upcomingEvent->image_url ? 'background-image:url(\'' . $upcomingEvent->image_url . '\');' : '' }}"></div>
    <div class="feature-content">
      <div class="dash-tag">🏷 Upcoming Event</div>
      <h2>{{ $upcomingEvent->title }}</h2>
      @if($upcomingEvent->description)
      <p class="desc">{{ \Illuminate\Support\Str::limit($upcomingEvent->description, 140) }}</p>
      @endif
      <div class="feature-meta">
        <span>📅 {{ $upcomingEvent->date_start->format('F j, Y') }}@if(!$upcomingEvent->date_start->isSameDay($upcomingEvent->date_end)) – {{ $upcomingEvent->date_end->format('F j, Y') }}@endif</span>
        @if($upcomingEvent->venue)
        <span>📍 {{ $upcomingEvent->venue }}</span>
        @endif
      </div>
      @if($permissions['manage_events'] || $permissions['manage_attendance'])
      <a href="{{ route('officer.events.show', $upcomingEvent) }}" class="btn btn-primary" style="width:auto; padding:0 22px; display:inline-flex;">View Event Details →</a>
      @endif
    </div>
  </div>
  @else
  <div class="empty-feature">No upcoming events yet.@if($permissions['manage_events']) <a href="{{ route('officer.events.create') }}" class="link-sm">Create one →</a>@endif</div>
  @endif

  <div class="announce-card">
    <div class="dash-tag">📣 Latest Announcement
      @if($permissions['draft_announcements'] || $permissions['manage_announcements'])
      <a href="{{ route('officer.announcements.index') }}" class="view-all">View all</a>
      @endif
    </div>
    @if($latestAnnouncement)
      <h4>{{ $latestAnnouncement->title }}</h4>
      <p class="body-text">{{ $latestAnnouncement->body }}</p>
      <p class="when">{{ $latestAnnouncement->created_at->format('M j, Y \a\t g:i A') }}</p>
      @if($permissions['draft_announcements'] || $permissions['manage_announcements'])
      <a href="{{ route('officer.announcements.index') }}" class="link-sm">View all announcements →</a>
      @endif
    @else
      <p class="body-text" style="-webkit-line-clamp:unset;">No announcements published yet.</p>
    @endif
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Quick Access</h3></div>
  <div class="qa-grid">
    @if($permissions['manage_events'] || $permissions['manage_attendance'])
    <a href="{{ route('officer.events.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--primary-soft); color:var(--primary);">📅</div>
      <div class="qa-text"><b>Events</b><span>Manage events and view event list</span></div>
    </a>
    @endif
    @if($permissions['view_calendar'] || $permissions['manage_calendar'])
    <a href="{{ route('officer.calendar.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--purple-soft); color:var(--purple);">🗓</div>
      <div class="qa-text"><b>Calendar</b><span>View schedules and important dates</span></div>
    </a>
    @endif
    @if($isTreasurer)
    <a href="{{ route('officer.fines.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--emerald-soft); color:var(--emerald);">💲</div>
      <div class="qa-text"><b>Fines</b><span>Treasurer + Admin only access</span></div>
    </a>
    @endif
    @if($permissions['draft_announcements'] || $permissions['manage_announcements'])
    <a href="{{ route('officer.announcements.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--amber-soft); color:var(--amber);">📣</div>
      <div class="qa-text"><b>Announcements</b><span>Create and manage announcements</span></div>
    </a>
    @endif
  </div>
</div>

@endslot

</x-layout>
