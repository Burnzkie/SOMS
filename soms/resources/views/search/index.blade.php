<x-layout title="Search">

@slot('nav')
@if(auth()->user()->role === 'officer')
@include('partials.officer-nav')
@elseif(auth()->user()->role === 'student')
@include('partials.student-nav')
@elseif(auth()->user()->role === 'admin')
@include('partials.admin-nav')
@endif
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Search results</h1>
    <p class="meta">
      @if($q !== '')
        Showing matches for &ldquo;{{ $q }}&rdquo;
      @else
        Type something in the search box above to get started.
      @endif
    </p>
  </div>
</div>

@if($q !== '' && $events->isEmpty() && $announcements->isEmpty() && $fines->isEmpty() && $users->isEmpty() && $officerPositions->isEmpty() && $activityLogs->isEmpty())
<div class="panel">
  <p class="empty-note" style="padding:8px 0;">No matches for &ldquo;{{ $q }}&rdquo;. Try a different keyword.</p>
</div>
@endif

@if($events->isNotEmpty())
<div class="panel" style="margin-bottom:20px;">
  <div class="panel-head"><h3>Events</h3></div>
  <div class="qa-grid" style="grid-template-columns: 1fr;">
    @foreach($events as $event)
    <a href="{{ route(auth()->user()->role . '.events.show', $event) }}" class="qa-card">
      <div class="qa-icon" style="background:var(--primary-soft); color:var(--primary);">📅</div>
      <div class="qa-text">
        <b>{{ $event->title }}</b>
        <span>{{ $event->date_start->format('M j, Y') }}@if($event->venue) &middot; {{ $event->venue }} @endif</span>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

@if($announcements->isNotEmpty())
<div class="panel" style="margin-bottom:20px;">
  <div class="panel-head"><h3>Announcements</h3></div>
  <div class="qa-grid" style="grid-template-columns: 1fr;">
    @foreach($announcements as $announcement)
    <a href="{{ auth()->user()->role === 'student' ? route('student.announcements.show', $announcement) : route('officer.announcements.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--amber-soft); color:var(--amber);">📣</div>
      <div class="qa-text">
        <b>{{ $announcement->title }}</b>
        <span>{{ \Illuminate\Support\Str::limit($announcement->body, 70) }}</span>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

@if($fines->isNotEmpty())
<div class="panel" style="margin-bottom:20px;">
  <div class="panel-head"><h3>Fines</h3></div>
  <div class="qa-grid" style="grid-template-columns: 1fr;">
    @foreach($fines as $fine)
    <a href="{{ route(auth()->user()->role . '.fines.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--emerald-soft); color:var(--emerald);">💲</div>
      <div class="qa-text">
        <b>{{ $fine->event?->title ?? 'Unknown event' }} &middot; ₱{{ number_format($fine->amount, 2) }}</b>
        <span>
          @if(auth()->user()->role === 'officer'){{ $fine->user?->name }} &middot; @endif
          {{ ucfirst($fine->violation_type ?? $fine->status) }} &middot; {{ ucfirst($fine->status) }}
        </span>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

@if($users->isNotEmpty())
<div class="panel" style="margin-bottom:20px;">
  <div class="panel-head"><h3>Users</h3></div>
  <div class="qa-grid" style="grid-template-columns: 1fr;">
    @foreach($users as $user)
    <a href="{{ route('admin.users.activity-log', $user) }}" class="qa-card">
      <div class="qa-icon" style="background:var(--purple-soft); color:var(--purple);">👤</div>
      <div class="qa-text">
        <b>{{ $user->name }}</b>
        <span>{{ $user->student_id }} &middot; {{ ucfirst($user->role) }}</span>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

@if($officerPositions->isNotEmpty())
<div class="panel" style="margin-bottom:20px;">
  <div class="panel-head"><h3>Officer appointments</h3></div>
  <div class="qa-grid" style="grid-template-columns: 1fr;">
    @foreach($officerPositions as $position)
    <a href="{{ route('admin.officers.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--amber-soft); color:var(--amber);">🎖️</div>
      <div class="qa-text">
        <b>{{ $position->user?->name }} &middot; {{ $position->position_title }}</b>
        <span>{{ $position->academic_year }} &middot; {{ $position->is_active ? 'Active' : 'Inactive' }}</span>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif

@if($activityLogs->isNotEmpty())
<div class="panel" style="margin-bottom:20px;">
  <div class="panel-head"><h3>Activity logs</h3></div>
  <div class="qa-grid" style="grid-template-columns: 1fr;">
    @foreach($activityLogs as $log)
    <a href="{{ route('admin.activity-logs.index') }}" class="qa-card">
      <div class="qa-icon" style="background:var(--rose-soft); color:var(--rose);">🧾</div>
      <div class="qa-text">
        <b>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</b>
        <span>{{ $log->user?->name ?? 'System' }} &middot; {{ $log->created_at->diffForHumans() }}</span>
      </div>
    </a>
    @endforeach
  </div>
</div>
@endif
@endslot

</x-layout>
