<x-layout :title="$event->title">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>{{ $event->title }}</h1>
    <p class="meta">{{ $event->date_start->format('M j') }} – {{ $event->date_end->format('M j, Y') }} &middot; {{ $event->venue ?: 'No venue set' }}</p>
  </div>
  <div>
    <span class="badge {{ $event->is_published ? 'approved' : 'pending' }}">{{ $event->is_published ? 'Published' : 'Draft' }}</span>
    @unless($event->is_published)
    <form method="POST" action="{{ route('officer.events.publish', $event) }}" style="display:inline;">
      @csrf
      <button class="btn btn-primary" style="width:auto; padding:0 16px; margin-left:8px;">Publish</button>
    </form>
    @endunless
  </div>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif

<div class="panel">
  <div class="panel-head"><h3>Fine amounts</h3></div>
  <form method="POST" action="{{ route('officer.events.fine-rules', $event) }}">
    @csrf
    <div class="permission-grid" style="margin-bottom:14px;">
      @foreach($event->fineRules as $rule)
      <div class="field">
        <label style="font-size:11px; color:var(--text-muted);">{{ ucwords(str_replace('_', ' ', $rule->violation_type)) }}</label>
        <input type="number" step="0.01" min="0" name="amounts[{{ $rule->violation_type }}]" value="{{ $rule->amount }}" class="field-input" style="width:100%;">
      </div>
      @endforeach
    </div>
    <button class="btn btn-ghost" style="width:auto; padding:0 16px;">Save amounts</button>
  </form>
</div>

@foreach($event->eventDays as $day)
<div class="panel">
  <div class="panel-head"><h3>{{ $day->date->format('l, M j, Y') }}</h3></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Session</th>
          <th>Time-in window</th>
          <th>Time-out window</th>
          <th>Fines issued</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($day->sessions as $session)
        <tr>
          <td>{{ ucfirst($session->session_type) }}</td>
          <td>{{ $session->timein_start->format('g:i A') }} – {{ $session->timein_end->format('g:i A') }}</td>
          <td>{{ $session->timeout_start ? $session->timeout_start->format('g:i A') . ' – ' . $session->timeout_end->format('g:i A') : '—' }}</td>
          <td><span class="badge {{ $session->fines_issued ? 'approved' : 'pending' }}">{{ $session->fines_issued ? 'Yes' : 'No' }}</span></td>
          <td class="queue-actions">
            <a href="{{ route('officer.attendance.station', $session) }}" class="mini-btn approve">Scan</a>
            <form method="POST" action="{{ route('officer.attendance.close', $session) }}" onsubmit="return confirm('Close this session and issue fines now?');">
              @csrf
              <button class="mini-btn reject">Close</button>
            </form>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="padding:4px 8px 16px;">
            <div style="background:var(--surface-2); border-radius:var(--radius-md); padding:12px 14px;">
              <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">
                Delegates — {{ ucfirst($session->session_type) }}
              </div>
              @forelse($session->delegates as $delegate)
                <div class="queue-item" style="margin-bottom:6px;">
                  <div class="who">
                    <b>{{ $delegate->user->name }}</b>
                    <span>{{ $delegate->user->student_id }}</span>
                  </div>
                  <div class="queue-actions">
                    <form method="POST" action="{{ route('officer.delegates.destroy', [$session, $delegate]) }}" onsubmit="return confirm('Remove delegate access for {{ $delegate->user->name }}?');">
                      @csrf
                      @method('DELETE')
                      <button class="mini-btn reject">Remove</button>
                    </form>
                  </div>
                </div>
              @empty
                <p class="empty-note" style="margin-bottom:8px;">No delegates assigned — only Executive/Administrative officers can scan or override this session.</p>
              @endforelse
              <form method="POST" action="{{ route('officer.delegates.store', $session) }}" style="display:flex; gap:8px; margin-top:6px;">
                @csrf
                <input type="text" name="student_id" placeholder="Student ID (e.g. P1152302037)" maxlength="11" class="field-input" style="flex:1;" required>
                <button class="btn btn-ghost" style="width:auto; padding:0 14px;">Assign delegate</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endforeach
@endslot

</x-layout>
