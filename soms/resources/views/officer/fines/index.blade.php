<x-layout title="Fines">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Fines</h1>
    <p class="meta">{{ $fines->total() }} total &middot; Treasurer / Admin only</p>
  </div>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif

<div class="panel">
  <form method="GET" class="filter-form">
    <select name="status" class="field-input" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <option value="unpaid" @selected(request('status')==='unpaid')>Unpaid</option>
      <option value="paid" @selected(request('status')==='paid')>Paid</option>
      <option value="waived" @selected(request('status')==='waived')>Waived</option>
    </select>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Violation</th>
          <th>Event</th>
          <th>Amount</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($fines as $fine)
        <tr>
          <td class="user-cell">
            <div>{{ $fine->user->name }}</div>
            <div style="font-size:11px; color:var(--text-muted);">{{ $fine->user->student_id }}</div>
          </td>
          <td>{{ ucwords(str_replace('_', ' ', $fine->violation_type)) }}</td>
          <td>{{ $fine->event->title ?? '—' }}</td>
          <td>₱{{ number_format($fine->amount, 2) }}</td>
          <td><span class="badge {{ $fine->status }}">{{ ucfirst($fine->status) }}</span></td>
          <td class="queue-actions">
            @if($fine->status === 'unpaid')
            <form method="POST" action="{{ route('officer.fines.clear', $fine) }}" onsubmit="return promptReason(this, 'Paid in person');">
              @csrf
              <input type="hidden" name="reason" value="Paid in person">
              <button class="mini-btn approve">Mark paid</button>
            </form>
            <form method="POST" action="{{ route('officer.fines.waive', $fine) }}" onsubmit="return promptReason(this, 'Waived');">
              @csrf
              <input type="hidden" name="reason" value="Waived">
              <button class="mini-btn reject">Waive</button>
            </form>
            @else
            <span style="font-size:11px; color:var(--text-faint);">{{ $fine->reason }}</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="6" class="empty-note" style="padding:16px 8px;">No fines on record.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px;">{{ $fines->links() }}</div>
</div>

<script>
function promptReason(form, defaultText) {
  var reason = prompt('Reason (min 5 characters):', defaultText);
  if (reason === null || reason.trim().length < 5) { return false; }
  form.querySelector('input[name="reason"]').value = reason.trim();
  return true;
}
</script>
@endslot

</x-layout>
