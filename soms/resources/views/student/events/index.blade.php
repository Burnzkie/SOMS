<x-layout title="Events">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Events</h1>
    <p class="meta">{{ $events->total() }} total</p>
  </div>
</div>

<div class="panel">
  @forelse($events as $event)
    <div class="queue-item">
      <div class="who">
        <b>{{ $event->title }}</b>
        <span>{{ $event->date_start->format('M j') }} – {{ $event->date_end->format('M j, Y') }} · {{ $event->venue }}</span>
      </div>
      <a href="{{ route('student.events.show', $event) }}" class="link-sm" style="margin-left:auto;">View</a>
    </div>
  @empty
    <p class="empty-note">No events yet.</p>
  @endforelse
  <div style="margin-top:16px;">{{ $events->links() }}</div>
</div>
@endslot

</x-layout>
