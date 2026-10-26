{{-- resources/views/officer/audit/fines.blade.php --}}
<x-layout title="Fines & Payments (Audit)">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Fines &amp; Payments</h1>
    <p class="meta">{{ $fines->total() }} fines &middot; read-only audit view</p>
  </div>
  @if($canExport)
  <div><a href="{{ route('officer.audit.export.fines', request()->query()) }}" class="btn btn-ghost">Export CSV</a></div>
  @endif
</div>

<div class="panel">
  <form method="GET" class="filter-form">
    <select name="status" class="field-input" onchange="this.form.submit()">
      <option value="">All statuses</option>
      @foreach(['unpaid', 'paid', 'waived'] as $s)
        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <select name="event_id" class="field-input" onchange="this.form.submit()">
      <option value="">All events</option>
      @foreach($events as $event)
        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
      @endforeach
    </select>
    <input type="text" name="q" value="{{ request('q') }}" class="field-input" placeholder="Student name, ID or receipt no.">
    <button class="btn btn-ghost" style="width:auto; padding:0 18px;">Search</button>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student</th><th>Event</th><th>Violation</th><th>Amount</th><th>Status</th>
          <th>Settled by</th><th>Receipt no.</th><th>Reason</th>
        </tr>
      </thead>
      <tbody>
        @forelse($fines as $fine)
        <tr>
          <td>{{ $fine->user?->name }}<br><span style="font-size:11px; color:var(--text-faint);">{{ $fine->user?->student_id }}</span></td>
          <td>{{ $fine->event?->title }}</td>
          <td style="font-size:12px;">{{ str_replace('_', ' ', $fine->violation_type) }}</td>
          <td>₱{{ number_format((float) $fine->amount, 2) }}</td>
          <td>@include('partials.audit-badge', ['kind' => 'fine', 'value' => $fine->status])</td>
          <td style="font-size:12px;">
            @if($fine->clearedBy){{ $fine->clearedBy->name }}<br>{{ $fine->cleared_at?->format('M j, Y g:i A') }}@else — @endif
          </td>
          <td>{{ $fine->receipt_number ?? '—' }}</td>
          <td style="font-size:12px;">{{ $fine->reason }}</td>
        </tr>
        @empty
        <tr><td colspan="8" class="empty-note">No fines match.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="pagination-wrap">{{ $fines->links() }}</div>
</div>
@endslot

</x-layout>
