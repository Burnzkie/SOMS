{{-- resources/views/officer/audit/attendance.blade.php --}}
<x-layout title="Attendance Records (Audit)">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Attendance Records</h1>
    <p class="meta">Read-only. Totals per session, flagged-scan history and manual overrides.</p>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Sessions</h3></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Event</th><th>Session</th><th>Time-in window</th><th>Present</th>
          <th>Flagged (pending)</th><th>Approved</th><th>Rejected</th><th>Overrides</th><th>Fines</th>
        </tr>
      </thead>
      <tbody>
        @forelse($sessions as $session)
        @php($c = $counts[$session->id] ?? null)
        <tr>
          <td>{{ $session->eventDay->event->title }}</td>
          <td>{{ $session->label }}</td>
          <td style="font-size:12px;">{{ $session->timein_start->format('M j, g:i A') }} – {{ $session->timein_end->format('g:i A') }}</td>
          <td>{{ $c->present_count ?? 0 }}</td>
          <td>{{ $c->pending_count ?? 0 }}</td>
          <td>{{ $c->approved_count ?? 0 }}</td>
          <td>{{ $c->rejected_count ?? 0 }}</td>
          <td>{{ $c->override_count ?? 0 }}</td>
          <td>{{ $fineCounts[$session->id] ?? 0 }}{{ $session->fines_issued ? '' : ' (not issued yet)' }}</td>
        </tr>
        @empty
        <tr><td colspan="9" class="empty-note">No sessions yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="pagination-wrap">{{ $sessions->links() }}</div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Flagged scans &mdash; history</h3></div>
  <p class="empty-note" style="text-align:left;">Offline scans held for review because the device clock was off by more than 10 minutes, and what was decided.</p>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Student</th><th>Event / session</th><th>Scan</th><th>Device clock</th><th>Received</th><th>Decision</th><th>Reviewed by</th></tr></thead>
      <tbody>
        @forelse($flaggedHistory as $scan)
        <tr>
          <td>{{ $scan->user->name }}<br><span style="font-size:11px; color:var(--text-faint);">{{ $scan->user->student_id }}</span></td>
          <td style="font-size:12px;">{{ $scan->eventSession->eventDay->event->title }} &middot; {{ $scan->eventSession->label }}</td>
          <td>{{ $scan->scan_type === 'time_out' ? 'Time out' : 'Time in' }}</td>
          <td>{{ $scan->device_scanned_at?->format('M j, g:i A') ?? '—' }}</td>
          <td>{{ $scan->scanned_at->format('M j, g:i A') }}</td>
          <td>
            @if($scan->review_decision)
              <span class="badge {{ $scan->review_decision === 'approved' ? 'present' : 'absent' }}">{{ ucfirst($scan->review_decision) }}</span>
            @else
              <span class="badge pending">Pending</span>
            @endif
          </td>
          <td style="font-size:12px;">{{ $scan->reviewer?->name ?? '—' }}<br>{{ $scan->reviewed_at?->format('M j, g:i A') }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="empty-note">No flagged scans.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Manual overrides</h3></div>
  <p class="empty-note" style="text-align:left;">Attendance an officer marked by hand (after re-entering their password). An override replaces the scan that was there before.</p>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Student</th><th>Event / session</th><th>Scan</th><th>Marked by</th><th>When</th><th>Reason</th></tr></thead>
      <tbody>
        @forelse($overrides as $row)
        <tr>
          <td>{{ $row->user->name }}<br><span style="font-size:11px; color:var(--text-faint);">{{ $row->user->student_id }}</span></td>
          <td style="font-size:12px;">{{ $row->eventSession->eventDay->event->title }} &middot; {{ $row->eventSession->label }}</td>
          <td>{{ $row->scan_type === 'time_out' ? 'Time out' : 'Time in' }}</td>
          <td>{{ $row->markedBy?->name ?? '—' }}</td>
          <td>{{ $row->scanned_at->format('M j, g:i A') }}</td>
          <td style="font-size:12px;">{{ $row->override_reason }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="empty-note">No manual overrides.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endslot

</x-layout>
