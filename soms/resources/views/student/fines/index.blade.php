<x-layout title="Fines">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Fines</h1>
    <p class="meta">{{ $fines->total() }} total</p>
  </div>
</div>

<div class="banner warn">Pay your fine in person at the Treasurer's office. Your fine will be marked Paid here once the Treasurer records your payment.</div>

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
          <th>Violation</th>
          <th>Event</th>
          <th>Amount</th>
          <th>Issued</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($fines as $fine)
        <tr>
          <td>{{ ucwords(str_replace('_', ' ', $fine->violation_type)) }}</td>
          <td>{{ $fine->event->title ?? '—' }}</td>
          <td>₱{{ number_format($fine->amount, 2) }}</td>
          <td>{{ $fine->issued_at?->format('M j, Y') }}</td>
          <td><span class="badge {{ $fine->status }}">{{ ucfirst($fine->status) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty-note" style="padding:16px 8px;">No fines on record.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px;">{{ $fines->links() }}</div>
</div>
@endslot

</x-layout>
