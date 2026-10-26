{{-- resources/views/officer/fines/index.blade.php --}}
<x-layout title="Fines">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Fines</h1>
    <p class="meta">{{ $summaries->total() }} students &middot; ₱{{ number_format($grandUnpaid, 2) }} unpaid &middot; Treasurer / Admin only</p>
  </div>
</div>

{{-- NOTE: the status banner was removed here because the layout already renders session('status')
     (your screenshot showed it twice). Re-add if the layout does not. --}}

<div class="panel">
  <form method="GET" class="filter-form">
    <select name="status" class="field-input" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <option value="unpaid" @selected(request('status')==='unpaid')>Has unpaid</option>
      <option value="paid" @selected(request('status')==='paid')>Has paid</option>
      <option value="waived" @selected(request('status')==='waived')>Has waived</option>
    </select>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Unpaid total</th>
          <th>Fines</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($summaries as $row)
        @php
          $student = $users[$row->user_id];
          $studentFines = $finesByUser[$row->user_id] ?? collect();
        @endphp
        <tr>
          <td class="user-cell">
            <div>{{ $student->name }}</div>
            <div style="font-size:11px; color:var(--text-muted);">{{ $student->student_id }}</div>
          </td>
          <td><strong>₱{{ number_format($row->unpaid_total, 2) }}</strong></td>
          <td style="font-size:12px;">{{ $row->unpaid_count }} unpaid &middot; {{ $row->paid_count }} paid &middot; {{ $row->waived_count }} waived</td>
          <td class="queue-actions">
            @if($row->unpaid_count > 0)
            <form method="POST" action="{{ route('officer.fines.clearAll', $student) }}" onsubmit="return promptPayment(this);">
              @csrf
              <input type="hidden" name="reason" value="Paid in person">
              <input type="hidden" name="receipt_number" value="">
              <button class="mini-btn approve">Mark all paid</button>
            </form>
            <form method="POST" action="{{ route('officer.fines.waiveAll', $student) }}" onsubmit="return promptReason(this, 'Waived');">
              @csrf
              <input type="hidden" name="reason" value="Waived">
              <button class="mini-btn reject">Waive all</button>
            </form>
            @else
            <span class="badge paid">Settled</span>
            @endif
            <button type="button" class="mini-btn" onclick="toggleDetails({{ $row->user_id }})">Details</button>
          </td>
        </tr>

        {{-- Expandable breakdown: per-fine actions stay available for partial payments --}}
        <tr id="details-{{ $row->user_id }}" hidden>
          <td colspan="4">
            <table class="data-table">
              <tbody>
                @foreach($studentFines as $fine)
                <tr>
                  <td>{{ ucwords(str_replace('_', ' ', $fine->violation_type)) }}</td>
                  <td>{{ $fine->event->title ?? '—' }}</td>
                  <td>₱{{ number_format($fine->amount, 2) }}</td>
                  <td><span class="badge {{ $fine->status }}">{{ ucfirst($fine->status) }}</span></td>
                  <td class="queue-actions">
                    @if($fine->status === 'unpaid')
                    <form method="POST" action="{{ route('officer.fines.clear', $fine) }}" onsubmit="return promptPayment(this);">
                      @csrf
                      <input type="hidden" name="reason" value="Paid in person">
                      <input type="hidden" name="receipt_number" value="">
                      <button class="mini-btn approve">Mark paid</button>
                    </form>
                    <form method="POST" action="{{ route('officer.fines.waive', $fine) }}" onsubmit="return promptReason(this, 'Waived');">
                      @csrf
                      <input type="hidden" name="reason" value="Waived">
                      <button class="mini-btn reject">Waive</button>
                    </form>
                    @else
                    <span style="font-size:11px; color:var(--text-faint);">{{ $fine->reason }}@if($fine->receipt_number) &middot; Receipt {{ $fine->receipt_number }}@endif</span>
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty-note" style="padding:16px 8px;">No fines on record.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px;">{{ $summaries->links() }}</div>
</div>

<script>
// Asks the treasurer for a reason (min 5 chars); cancels the submit if invalid.
function promptReason(form, defaultText) {
  var reason = prompt('Reason (min 5 characters):', defaultText);
  if (reason === null || reason.trim().length < 5) { return false; }
  form.querySelector('input[name="reason"]').value = reason.trim();
  return true;
}

// Marking paid: asks for the reason, then the receipt / OR number. The number is
// optional unless the system requires it; the Auditor's report flags paid fines without one.
function promptPayment(form) {
  if (!promptReason(form, 'Paid in person')) { return false; }

  var requireReceipt = {{ config('soms.require_payment_receipt') ? 'true' : 'false' }};
  var receipt = prompt('Receipt / OR number' + (requireReceipt ? ':' : ' (leave blank if none):'), '');
  if (receipt === null) { return false; }

  receipt = receipt.trim();
  if (requireReceipt && receipt === '') { alert('A receipt number is required.'); return false; }
  if (receipt !== '' && !/^[A-Za-z0-9][A-Za-z0-9 \-\/\.]*$/.test(receipt)) {
    alert('Receipt numbers may only contain letters, numbers, spaces and - / .');
    return false;
  }

  form.querySelector('input[name="receipt_number"]').value = receipt;
  return true;
}

// Show/hide a student's fine breakdown
function toggleDetails(userId) {
  var row = document.getElementById('details-' + userId);
  row.hidden = !row.hidden;
}

// Loading state: disable the button after a valid submit so a double-click can't fire twice
document.addEventListener('submit', function (e) {
  var btn = e.target.querySelector('button');
  if (btn && !e.defaultPrevented) { btn.disabled = true; btn.textContent = 'Processing…'; }
});
</script>
@endslot

</x-layout>