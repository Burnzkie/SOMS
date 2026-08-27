<x-layout title="Events & Attendance">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Events &amp; Attendance</h1>
    <p class="meta">{{ $events->total() }} total</p>
  </div>
  <a href="{{ route('officer.events.create') }}" class="btn btn-primary" style="width:auto; padding:0 18px;">New event</a>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif

<div class="panel">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Type</th>
          <th>Dates</th>
          <th>Days</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($events as $event)
        <tr>
          <td>{{ $event->title }}</td>
          <td>{{ $event->type === 'foundation_day' ? 'Foundation Day' : 'Other' }}</td>
          <td>{{ $event->date_start->format('M j') }} – {{ $event->date_end->format('M j, Y') }}</td>
          <td>{{ $event->event_days_count }}</td>
          <td><span class="badge {{ $event->is_published ? 'approved' : 'pending' }}">{{ $event->is_published ? 'Published' : 'Draft' }}</span></td>
          <td><a href="{{ route('officer.events.show', $event) }}" class="link-sm">Manage</a></td>
        </tr>
        @empty
        <tr><td colspan="6" class="empty-note" style="padding:16px 8px;">No events yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px;">{{ $events->links() }}</div>
</div>
@endslot

</x-layout>
