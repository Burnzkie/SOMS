<x-layout title="Announcements">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Announcements</h1>
    <p class="meta">{{ $announcements->total() }} total</p>
  </div>
  <a href="{{ route('officer.announcements.create') }}" class="btn btn-primary" style="width:auto; padding:0 18px;">New draft</a>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif

<div class="panel">
  @forelse($announcements as $a)
  <div class="queue-item">
    <div class="who">
      <b>{{ $a->title }}</b>
      <span><span class="badge {{ $a->is_published ? 'approved' : 'pending' }}">{{ $a->is_published ? 'Published' : 'Draft' }}</span> &middot; {{ $a->created_at->format('M j, Y') }}</span>
    </div>
    @if($canPublish)
    <div class="queue-actions">
      @if(!$a->is_published)
      <form method="POST" action="{{ route('officer.announcements.publish', $a) }}">@csrf<button class="mini-btn approve">Publish</button></form>
      @else
      <form method="POST" action="{{ route('officer.announcements.unpublish', $a) }}">@csrf<button class="mini-btn reject">Unpublish</button></form>
      @endif
    </div>
    @endif
  </div>
  @empty
  <p class="empty-note">No announcements yet.</p>
  @endforelse
  <div style="margin-top:16px;">{{ $announcements->links() }}</div>
</div>
@endslot

</x-layout>
