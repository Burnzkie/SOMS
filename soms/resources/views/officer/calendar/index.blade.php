<x-layout title="Calendar">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.css" rel="stylesheet">
<style>
#calendar{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px; box-shadow:var(--shadow-sm); }
.fc{ --fc-border-color:var(--border); --fc-page-bg-color:transparent; --fc-neutral-bg-color:var(--surface-2); color:var(--text); font-family:var(--font-ui); }
.fc .fc-toolbar-title{ font-family:var(--font-display); font-size:18px; }
.fc .fc-button{ background:var(--surface-2); border:1px solid var(--border); color:var(--text); box-shadow:none; text-transform:capitalize; }
.fc .fc-button:hover{ background:var(--primary-soft); color:var(--primary); }
.fc .fc-button-primary:not(:disabled).fc-button-active{ background:var(--primary); border-color:var(--primary); color:#fff; }
.fc-daygrid-day.fc-day-today{ background:var(--primary-soft) !important; }
.legend{ display:flex; gap:18px; margin-bottom:14px; font-size:12.5px; color:var(--text-muted); }
.legend .dot{ display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:6px; }
</style>
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Calendar</h1>
    <p class="meta">SOMS events and custom entries for your organization.</p>
  </div>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif

<div class="legend">
  <span><span class="dot" style="background:#5B5BF6;"></span>SOMS Events</span>
  <span><span class="dot" style="background:#8C90A3;"></span>Custom Entries</span>
</div>

<div id="calendar"></div>

@if($canManage)
<div class="panel" style="margin-top:20px;">
  <div class="panel-head"><h3>Custom entries</h3></div>

  <form method="POST" action="{{ route('officer.calendar.store') }}" style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
    @csrf
    <input type="text" name="title" placeholder="Entry title" class="field-input" style="flex:2; min-width:160px;" required>
    <input type="date" name="date" class="field-input" style="flex:1; min-width:140px;" required>
    <input type="text" name="notes" placeholder="Notes (optional)" class="field-input" style="flex:2; min-width:160px;">
    <button class="btn btn-primary" style="width:auto; padding:0 16px;">Add entry</button>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Title</th><th>Date</th><th>Notes</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($entries as $entry)
        <tr>
          <td>{{ $entry->title }}</td>
          <td>{{ $entry->date->format('M j, Y') }}</td>
          <td>{{ $entry->notes ?: '—' }}</td>
          <td>
            <form method="POST" action="{{ route('officer.calendar.destroy', $entry) }}" onsubmit="return confirm('Remove this entry?');">
              @csrf
              @method('DELETE')
              <button class="mini-btn reject">Remove</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty-note">No custom entries yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif

<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
    events: {{ $eventsJson }},
  });
  calendar.render();
});
</script>
@endslot

</x-layout>
