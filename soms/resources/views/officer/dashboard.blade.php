<x-layout title="Officer Dashboard">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('styles')
<style>
.nav-link-soon{
  display:flex;
  align-items:center;
  gap:10px;
  padding:9px 12px;
  border-radius:var(--radius-sm);
  font-size:13.5px;
  font-weight:500;
  color:var(--text-faint);
  margin-bottom:2px;
  cursor:default;
}
.soon-tag{
  margin-left:auto;
  font-size:9px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.04em;
  background:var(--surface-2);
  color:var(--text-faint);
  padding:2px 7px;
  border-radius:99px;
}
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
.permission-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));
  gap:10px;
}
.permission-pill{
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 12px;
  border-radius:var(--radius-sm);
  background:var(--surface-2);
  font-size:12.5px;
  font-weight:600;
  color:var(--text-muted);
}
.permission-pill.granted{ color:var(--emerald);}
.permission-pill .dot{
  width:7px;
  height:7px;
  border-radius:50%;
  background:var(--text-faint);
  flex-shrink:0;
}
.permission-pill.granted .dot{ background:var(--emerald);}
</style>
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <p class="meta">{{ now()->format('l, F j, Y') }}</p>
    @if($position)
      <div class="tier-badge">{{ $position->position_title }} &middot; {{ $tier }} tier</div>
    @endif
  </div>
</div>

@if($isTreasurer)
<div class="banner warn">💰 As Treasurer, you have access to fine management — Fines is in the sidebar.</div>
@endif

<div class="panel">
  <div class="panel-head">
    <h3>Your permissions</h3>
    <span class="note">Based on your position tier</span>
  </div>
  <div class="permission-grid">
    <div class="permission-pill {{ $permissions['manage_events'] ? 'granted' : '' }}"><span class="dot"></span>Manage events</div>
    <div class="permission-pill {{ $permissions['manage_attendance'] ? 'granted' : '' }}"><span class="dot"></span>Manage attendance</div>
    <div class="permission-pill {{ $isTreasurer ? 'granted' : '' }}"><span class="dot"></span>Manage fines</div>
    <div class="permission-pill {{ $permissions['manage_announcements'] ? 'granted' : '' }}"><span class="dot"></span>Publish announcements</div>
    <div class="permission-pill {{ $permissions['draft_announcements'] ? 'granted' : '' }}"><span class="dot"></span>Draft announcements</div>
    <div class="permission-pill {{ $permissions['manage_members'] ? 'granted' : '' }}"><span class="dot"></span>Manage members</div>
    <div class="permission-pill {{ $permissions['manage_calendar'] ? 'granted' : '' }}"><span class="dot"></span>Manage calendar</div>
    <div class="permission-pill {{ $permissions['view_reports'] ? 'granted' : '' }}"><span class="dot"></span>View reports</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>Quick links</h3>
  </div>
  <div class="permission-grid">
    @if($permissions['manage_events'] || $permissions['manage_attendance'])
    <a href="{{ route('officer.events.index') }}" class="permission-pill granted" style="cursor:pointer; text-decoration:none;">Events &amp; attendance</a>
    @endif
    @if($isTreasurer)
    <a href="{{ route('officer.fines.index') }}" class="permission-pill granted" style="cursor:pointer; text-decoration:none;">Fines</a>
    @endif
    @if($permissions['draft_announcements'] || $permissions['manage_announcements'])
    <a href="{{ route('officer.announcements.index') }}" class="permission-pill granted" style="cursor:pointer; text-decoration:none;">Announcements</a>
    @endif
    @if($permissions['view_calendar'] || $permissions['manage_calendar'])
    <a href="{{ route('officer.calendar.index') }}" class="permission-pill granted" style="cursor:pointer; text-decoration:none;">Calendar</a>
    @endif
  </div>
</div>
@endslot

</x-layout>
