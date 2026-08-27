<x-layout title="Announcements">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Announcements</h1>
    <p class="meta">{{ $announcements->count() }} total</p>
  </div>
</div>

<div class="panel">
  @forelse($announcements as $announcement)
    <div class="queue-item">
      <div class="who">
        <b>{{ $announcement->title }}</b>
        <span>{{ $announcement->created_at->diffForHumans() }}</span>
      </div>
      <a href="{{ route('student.announcements.show', $announcement) }}" class="link-sm" style="margin-left:auto;">Read</a>
    </div>
  @empty
    <p class="empty-note">No announcements yet.</p>
  @endforelse
</div>
@endslot

</x-layout>
